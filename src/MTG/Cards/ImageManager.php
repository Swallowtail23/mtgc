<?php

/*
Version:     1.26
Date:        26/08/26
Name:        ImageManager.php
Purpose:     Resolves and downloads locally cached Scryfall card images.
Notes:       Prefers WebP, retains JPEG fallback, and fills missing UI cache entries on demand.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;
use MTG\Core\Network\RemoteFileChecker;
use MTG\Core\UserAgent;

class ImageManager
{
    private const WEBP_EXTENSION = '.webp';
    private const JPEG_EXTENSION = '.jpg';
    private const PLACEHOLDER_IMAGE = '/images/back.jpg';

    /** @var \mysqli|object */
    private $db;
    private string $adminEmail;
    private Message $message;
    private AppConfig $appConfig;
    private GameRules $gameRules;

    /** @param \mysqli|object $db */
    public function __construct($db, AppConfig $appConfig, GameRules $gameRules)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->gameRules = $gameRules;
        $this->adminEmail = (string) $this->appConfig->email('adminEmail', '');
        $this->message = new Message($this->appConfig);
    }

    /** @return array{front: string, back: string} */
    public function getImage(string $setcode, string $cardId, string $layout, bool $allowFetch = true): array
    {
        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $allowFetchLabel = $allowFetch ? 'true' : 'false';
        $this->message->logMessage(
            '[DEBUG]',
            "Image lookup for $setcode, $cardId, $layout (fetch $allowFetchLabel)"
        );

        $cardImages = $this->getCardImageUris($cardId);
        $front = $this->resolveImageFace(
            $cardImages['front'],
            $imgLocation,
            $setcode,
            $cardId,
            $allowFetch
        );
        $back = '';

        if ($this->layoutHasSeparateBack($layout)) :
            $back = $this->resolveImageFace(
                $cardImages['back'],
                $imgLocation,
                $setcode,
                $cardId . '_b',
                $allowFetch
            );
        endif;

        return ['front' => $front, 'back' => $back];
    }

    /** @return array{success: bool, front: string, back: string} */
    public function refreshImage(string $cardId): array
    {
        $this->message->logMessage('[DEBUG]', "Explicit image refresh called for $cardId");

        try {
            $cardData = $this->getCardImageUris($cardId);
        } catch (\Throwable $exception) {
            $this->message->logMessage('[ERROR]', "Unable to load image data for $cardId: {$exception->getMessage()}");
            return ['success' => false, 'front' => '', 'back' => ''];
        }

        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $front = $this->refreshImageFace(
            $cardData['front'],
            $imgLocation,
            $cardData['setcode'],
            $cardId
        );
        $back = '';
        $backRequired = $this->layoutHasSeparateBack($cardData['layout']) && $cardData['back'] !== '';

        if ($backRequired) :
            $back = $this->refreshImageFace(
                $cardData['back'],
                $imgLocation,
                $cardData['setcode'],
                $cardId . '_b'
            );
        endif;

        $success = $this->isCachedImageResult($front)
            && (!$backRequired || $this->isCachedImageResult($back));
        if (!$success) :
            $this->sendRefreshFailureNotice($cardId, $front, $back);
            return ['success' => false, 'front' => '', 'back' => ''];
        endif;

        $this->message->logMessage(
            '[DEBUG]',
            "Explicit image refresh complete for $cardId. Front: $front; Back: $back"
        );
        return ['success' => true, 'front' => $front, 'back' => $back];
    }

    /** @return array{front: string, front_changed: bool, back: string, back_changed: bool} */
    public function checkAndRefreshImage(string $cardId): array
    {
        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $cardData = $this->getCardImageUris($cardId);

        $front = $this->resolveImageFaceResult(
            $cardData['front'],
            $imgLocation,
            $cardData['setcode'],
            $cardId,
            true
        );

        $back = ['path' => '', 'changed' => false];
        if ($this->layoutHasSeparateBack($cardData['layout'])) :
            $back = $this->resolveImageFaceResult(
                $cardData['back'],
                $imgLocation,
                $cardData['setcode'],
                $cardId . '_b',
                true
            );
        endif;

        return [
            'front' => $front['path'],
            'front_changed' => $front['changed'],
            'back' => $back['path'],
            'back_changed' => $back['changed'],
        ];
    }

    /** @return array{front: string, back: string, setcode: string, layout: string} */
    private function getCardImageUris(string $cardId): array
    {
        $sql = "SELECT image_uri, f1_image_uri, f2_image_uri, setcode, layout
                FROM cards_scry
                WHERE id = ?
                LIMIT 1";
        $result = $this->db->execute_query($sql, [$cardId]);

        if ($result === false) :
            $databaseError = (string) ($this->db->error ?? 'unknown database error');
            throw new \Exception("Unable to load image URIs for $cardId: $databaseError");
        endif;

        $row = $result->fetch_array(MYSQLI_ASSOC);
        if (!is_array($row)) :
            throw new \Exception("No image record found for $cardId");
        endif;

        $front = '';
        if (isset($row['image_uri']) && $row['image_uri'] !== null) :
            $front = (string) $row['image_uri'];
        elseif (isset($row['f1_image_uri']) && $row['f1_image_uri'] !== null) :
            $front = (string) $row['f1_image_uri'];
        endif;

        $back = isset($row['f2_image_uri']) && $row['f2_image_uri'] !== null
            ? (string) $row['f2_image_uri']
            : '';

        return [
            'front' => trim($front),
            'back' => trim($back),
            'setcode' => (string) $row['setcode'],
            'layout' => (string) $row['layout'],
        ];
    }

    private function resolveImageFace(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $fileStem,
        bool $allowFetch
    ): string {
        $result = $this->resolveImageFaceResult($remoteUrl, $imgLocation, $setcode, $fileStem, $allowFetch);
        return $result['path'];
    }

    /** @return array{path: string, changed: bool} */
    private function resolveImageFaceResult(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $fileStem,
        bool $allowFetch
    ): array {
        $basePath = $imgLocation . $setcode . '/' . $fileStem;
        foreach ($this->cacheCandidates($basePath) as $candidate) :
            if ($this->isReadable($candidate)) :
                $this->message->logMessage('[DEBUG]', "Using cached image $candidate");
                return ['path' => $this->relativeImagePath($candidate), 'changed' => false];
            endif;
            if ($this->fileExists($candidate)) :
                $this->message->logMessage('[DEBUG]', "Cached image is not readable at $candidate");
            endif;
        endforeach;

        if (!$allowFetch) :
            $this->message->logMessage(
                '[DEBUG]',
                "Image cache miss for $fileStem; returning placeholder before asynchronous resolution"
            );
            return ['path' => self::PLACEHOLDER_IMAGE, 'changed' => false];
        endif;

        $destination = $basePath . $this->extensionForRemoteUrl($remoteUrl);
        $this->message->logMessage('[DEBUG]', "Image cache miss for $fileStem; downloading $remoteUrl");
        $path = $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $destination);
        return ['path' => $path, 'changed' => $this->isCachedImageResult($path)];
    }

    private function refreshImageFace(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $fileStem
    ): string {
        if ($remoteUrl === '') :
            return 'empty';
        endif;

        $basePath = $imgLocation . $setcode . '/' . $fileStem;
        $destination = $basePath . $this->extensionForRemoteUrl($remoteUrl);
        $result = $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $destination);
        if (!$this->isCachedImageResult($result)) :
            return $result;
        endif;

        foreach ($this->cacheCandidates($basePath) as $candidate) :
            if ($candidate === $destination || !$this->fileExists($candidate)) :
                continue;
            endif;
            if (!@unlink($candidate)) :
                $this->message->logMessage('[ERROR]', "Unable to remove superseded image $candidate");
            else :
                $this->message->logMessage('[DEBUG]', "Removed superseded image $candidate");
            endif;
        endforeach;

        return $result;
    }

    private function fetchAndStoreImage(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $destination
    ): string {
        if ($remoteUrl === '') :
            return 'empty';
        endif;

        if (!RemoteFileChecker::exists($remoteUrl, $this->appConfig, $this->message)) :
            $this->message->logMessage('[ERROR]', "Scryfall image does not exist: $remoteUrl");
            return 'error';
        endif;

        $userAgent = UserAgent::buildFromConfig($this->appConfig, null, $this->message);
        $options = ['http' => ['user_agent' => $userAgent]];
        $context = stream_context_create($options);
        $image = @file_get_contents($remoteUrl, false, $context);
        if ($image === false) :
            $this->message->logMessage('[ERROR]', "Unable to download Scryfall image $remoteUrl");
            return 'error';
        endif;

        $directory = $imgLocation . $setcode;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) :
            $this->message->logMessage('[ERROR]', "Unable to create image directory $directory");
            return 'error';
        endif;

        $temporary = $destination . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $image, LOCK_EX) === false || !rename($temporary, $destination)) :
            @unlink($temporary);
            $this->message->logMessage('[ERROR]', "Unable to write Scryfall image $destination");
            return 'error';
        endif;

        $this->message->logMessage('[DEBUG]', "Stored Scryfall image $destination");
        return $this->relativeImagePath($destination);
    }

    /** @return array{0: string, 1: string} */
    private function cacheCandidates(string $basePath): array
    {
        return [
            $basePath . self::WEBP_EXTENSION,
            $basePath . self::JPEG_EXTENSION,
        ];
    }

    private function extensionForRemoteUrl(string $remoteUrl): string
    {
        $path = parse_url($remoteUrl, PHP_URL_PATH);
        if (is_string($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp') :
            return self::WEBP_EXTENSION;
        endif;
        return self::JPEG_EXTENSION;
    }

    private function relativeImagePath(string $path): string
    {
        $relativeStart = strpos($path, 'cardimg/');
        return $relativeStart === false ? $path : substr($path, $relativeStart);
    }

    private function layoutHasSeparateBack(string $layout): bool
    {
        $twoCardDetailSections = $this->gameRules->get('twoCardDetailSections', []);
        return is_array($twoCardDetailSections) && in_array($layout, $twoCardDetailSections, true);
    }

    private function isCachedImageResult(string $result): bool
    {
        return $result !== ''
            && $result !== 'empty'
            && $result !== 'error'
            && $result !== self::PLACEHOLDER_IMAGE;
    }

    private function sendRefreshFailureNotice(string $cardId, string $front, string $back): void
    {
        $subject = 'Image refresh failure';
        $body = "Failed image refresh for $cardId. Front: $front; Back: $back";
        if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
            $mail = new MyPHPMailer(true, $this->appConfig);
            $mail->sendEmail($this->adminEmail, false, $subject, $body);
            return;
        endif;

        $this->message->logMessage('[NOTICE]', "Email disabled; $body");
    }

    protected function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }
}
