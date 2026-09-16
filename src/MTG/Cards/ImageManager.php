<?php

/*
Version:     1.29
Date:        16/09/26
Name:        ImageManager.php
Purpose:     Resolves and downloads locally cached Scryfall card images.
Notes:       Prefers WebP, retains local JPEG fallback, and supports remote WebP cache migration.
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
    private const MAX_REMOTE_IMAGE_BYTES = 20 * 1024 * 1024;
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

    /** @return array{deleted: bool, failed: bool} */
    public function cleanupMigratedJpeg(array $faceResult): array
    {
        $status = (string) ($faceResult['status'] ?? '');
        $jpegPath = (string) ($faceResult['jpeg_path'] ?? '');
        if (!in_array($status, ['converted', 'already_webp'], true) || $jpegPath === '') :
            return ['deleted' => false, 'failed' => false];
        endif;
        if (!$this->fileExists($jpegPath)) :
            return ['deleted' => false, 'failed' => false];
        endif;

        if (@unlink($jpegPath)) :
            $this->message->logMessage('[DEBUG]', "Removed migrated JPEG $jpegPath");
            return ['deleted' => true, 'failed' => false];
        endif;

        $this->message->logMessage('[ERROR]', "Unable to remove migrated JPEG $jpegPath");
        return ['deleted' => false, 'failed' => true];
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

    /**
     * Fetch remote WebP variants for existing JPEG card cache files.
     *
     * The migration deliberately uses the remote image URL rather than
     * transcoding the locally stored JPEG. JPEG deletion is opt-in so the
     * command can be run once to populate WebP and again to reclaim space.
     *
     * @return array{front: array<string, mixed>, back: array<string, mixed>}
     */
    public function migrateCardToWebp(
        string $cardId,
        bool $deleteJpeg = false,
        bool $dryRun = false
    ): array {
        try {
            $cardData = $this->getCardImageUris($cardId);
        } catch (\Throwable $exception) {
            $this->message->logMessage(
                '[ERROR]',
                "Unable to load image data for $cardId: {$exception->getMessage()}"
            );
            return [
                'front' => $this->migrationFailure('card_lookup_failed'),
                'back' => $this->migrationFailure('card_lookup_failed'),
            ];
        }

        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $front = $this->migrateImageFace(
            $cardData['front'],
            $imgLocation,
            $cardData['setcode'],
            $cardId,
            $cardData['front_field'],
            $deleteJpeg,
            $dryRun
        );
        $back = $this->migrationNotRequired();

        if ($this->layoutHasSeparateBack($cardData['layout']) && $cardData['back'] !== '') :
            $back = $this->migrateImageFace(
                $cardData['back'],
                $imgLocation,
                $cardData['setcode'],
                $cardId . '_b',
                $cardData['back_field'],
                $deleteJpeg,
                $dryRun
            );
        endif;

        return ['front' => $front, 'back' => $back];
    }

    /** @return array{front: string, back: string, front_field: ?string, back_field: ?string, setcode: string, layout: string} */
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
        $frontField = null;
        if (isset($row['image_uri']) && trim((string) $row['image_uri']) !== '') :
            $front = (string) $row['image_uri'];
            $frontField = 'image_uri';
        elseif (isset($row['f1_image_uri']) && trim((string) $row['f1_image_uri']) !== '') :
            $front = (string) $row['f1_image_uri'];
            $frontField = 'f1_image_uri';
        endif;

        $back = isset($row['f2_image_uri']) && $row['f2_image_uri'] !== null
            ? (string) $row['f2_image_uri']
            : '';
        $backField = trim($back) === '' ? null : 'f2_image_uri';

        return [
            'front' => trim($front),
            'back' => trim($back),
            'front_field' => $frontField,
            'back_field' => $backField,
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

        $preferredRemoteUrl = $this->preferredRemoteImageUrl($remoteUrl);
        $destination = $basePath . $this->extensionForRemoteUrl($preferredRemoteUrl);
        $this->message->logMessage(
            '[DEBUG]',
            "Image cache miss for $fileStem; downloading $preferredRemoteUrl"
        );
        $path = $this->fetchAndStoreImage($preferredRemoteUrl, $imgLocation, $setcode, $destination);
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
        $preferredRemoteUrl = $this->preferredRemoteImageUrl($remoteUrl);
        $destination = $basePath . $this->extensionForRemoteUrl($preferredRemoteUrl);
        $result = $this->fetchAndStoreImage($preferredRemoteUrl, $imgLocation, $setcode, $destination);
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

    /**
     * @return array<string, mixed>
     */
    private function migrateImageFace(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $fileStem,
        ?string $databaseField,
        bool $deleteJpeg,
        bool $dryRun
    ): array {
        $basePath = $imgLocation . $setcode . '/' . $fileStem;
        $jpegPath = $basePath . self::JPEG_EXTENSION;
        $webpPath = $basePath . self::WEBP_EXTENSION;
        $jpegExists = $this->fileExists($jpegPath);
        $jpegBytes = $this->fileSize($jpegPath);
        $webpSource = $this->webpVariantUrl($remoteUrl);

        if ($this->isValidWebpFile($webpPath)) :
            $jpegDeleted = false;
            $cleanupFailed = false;
            if ($deleteJpeg && !$dryRun && $jpegExists) :
                if (@unlink($jpegPath)) :
                    $jpegDeleted = true;
                    $this->message->logMessage('[DEBUG]', "Removed superseded JPEG $jpegPath");
                else :
                    $cleanupFailed = true;
                    $this->message->logMessage('[ERROR]', "Unable to remove superseded JPEG $jpegPath");
                endif;
            endif;
            return [
                'status' => 'already_webp',
                'source' => $webpSource,
                'database_field' => $databaseField,
                'webp_path' => $webpPath,
                'jpeg_path' => $jpegPath,
                'jpeg_bytes' => $jpegBytes,
                'webp_bytes' => $this->fileSize($webpPath),
                'jpeg_deleted' => $jpegDeleted,
                'cleanup_failed' => $cleanupFailed,
            ];
        endif;

        if (!$jpegExists) :
            return $this->migrationResult(
                'missing_jpeg',
                $remoteUrl,
                $jpegPath,
                $webpPath,
                $jpegBytes,
                $databaseField
            );
        endif;

        $webpSources = $this->webpSourceUrls($remoteUrl);
        if ($webpSources === []) :
            $this->message->logMessage(
                '[ERROR]',
                "Unable to derive a remote WebP URL from $remoteUrl for $fileStem"
            );
            return $this->migrationResult(
                'missing_webp_source',
                $remoteUrl,
                $jpegPath,
                $webpPath,
                $jpegBytes,
                $databaseField
            );
        endif;

        if ($dryRun) :
            return $this->migrationResult(
                'dry_run',
                $webpSources[0],
                $jpegPath,
                $webpPath,
                $jpegBytes,
                $databaseField
            );
        endif;

        $sourceUrl = '';
        $result = 'error';
        foreach ($webpSources as $webpUrl) :
            $this->message->logMessage('[DEBUG]', "Migrating $jpegPath from remote WebP $webpUrl");
            $result = $this->fetchAndStoreImage(
                $webpUrl,
                $imgLocation,
                $setcode,
                $webpPath,
                'image/webp'
            );
            if ($this->isCachedImageResult($result)) :
                $sourceUrl = $webpUrl;
                break;
            endif;
        endforeach;
        if (!$this->isCachedImageResult($result)) :
            return $this->migrationResult(
                'download_failed',
                $remoteUrl,
                $jpegPath,
                $webpPath,
                $jpegBytes,
                $databaseField
            );
        endif;

        $jpegDeleted = false;
        $cleanupFailed = false;
        if ($deleteJpeg) :
            if (@unlink($jpegPath)) :
                $jpegDeleted = true;
                $this->message->logMessage('[DEBUG]', "Removed migrated JPEG $jpegPath");
            else :
                $cleanupFailed = true;
                $this->message->logMessage('[ERROR]', "Unable to remove migrated JPEG $jpegPath");
            endif;
        endif;

        return [
            'status' => 'converted',
            'source' => $sourceUrl,
            'database_field' => $databaseField,
            'webp_path' => $webpPath,
            'jpeg_path' => $jpegPath,
            'jpeg_bytes' => $jpegBytes,
            'webp_bytes' => $this->fileSize($webpPath),
            'jpeg_deleted' => $jpegDeleted,
            'cleanup_failed' => $cleanupFailed,
        ];
    }

    private function fetchAndStoreImage(
        string $remoteUrl,
        string $imgLocation,
        string $setcode,
        string $destination,
        ?string $expectedMime = null
    ): string {
        if ($remoteUrl === '') :
            return 'empty';
        endif;

        if ($expectedMime === null) :
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
        else :
            $image = $this->fetchRemoteImage($remoteUrl);
            if ($image === false) :
                return 'error';
            endif;
        endif;

        if ($expectedMime !== null && !$this->isValidImageBytes($image, $expectedMime)) :
            $this->message->logMessage(
                '[ERROR]',
                "Remote image $remoteUrl is not a valid $expectedMime image"
            );
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

    private function preferredRemoteImageUrl(string $remoteUrl): string
    {
        if (preg_match('/\.webp(?=$|[?#])/i', $remoteUrl) === 1) :
            return $remoteUrl;
        endif;
        if (!$this->isScryfallImageVariantUrl($remoteUrl)) :
            return $remoteUrl;
        endif;

        $webpUrl = $this->webpVariantUrl($remoteUrl);
        return $webpUrl === '' ? $remoteUrl : $webpUrl;
    }

    private function webpVariantUrl(string $remoteUrl): string
    {
        if (preg_match('/\.webp(?=$|[?#])/i', $remoteUrl) === 1) :
            return $remoteUrl;
        endif;

        if (preg_match('/\.jpe?g(?=$|[?#])/i', $remoteUrl) !== 1) :
            return '';
        endif;

        $webpUrl = preg_replace('/\.jpe?g(?=$|[?#])/i', '.webp', $remoteUrl, 1);
        if (!is_string($webpUrl)) :
            return '';
        endif;

        foreach (
            [
                'small' => 'thumb',
                'normal' => 'grid',
                'large' => 'display',
                'art_crop' => 'art',
                'border_crop' => 'crop',
            ] as $sourceVariant => $webpVariant
        ) :
            $candidate = preg_replace(
                '#/' . preg_quote($sourceVariant, '#') . '/#',
                '/' . $webpVariant . '/',
                $webpUrl,
                1
            );
            if (is_string($candidate) && $candidate !== $webpUrl) :
                return $candidate;
            endif;
        endforeach;

        return $webpUrl;
    }

    private function isScryfallImageVariantUrl(string $remoteUrl): bool
    {
        return preg_match(
            '#/(?:small|normal|large|art_crop|border_crop)/#i',
            $remoteUrl
        ) === 1;
    }

    /** @return array<int, string> */
    private function webpSourceUrls(string $remoteUrl): array
    {
        $webpUrl = $this->webpVariantUrl($remoteUrl);
        if ($webpUrl === '') :
            return [];
        endif;

        return [$webpUrl];
    }

    private function isValidWebpFile(string $path): bool
    {
        if (!$this->isReadable($path)) :
            return false;
        endif;

        $imageInfo = @getimagesize($path);
        return is_array($imageInfo) && ($imageInfo['mime'] ?? '') === 'image/webp';
    }

    private function isValidImageBytes(string $image, string $expectedMime): bool
    {
        $imageInfo = @getimagesizefromstring($image);
        return is_array($imageInfo) && ($imageInfo['mime'] ?? '') === $expectedMime;
    }

    private function fetchRemoteImage(string $remoteUrl): string|false
    {
        if (stripos($remoteUrl, 'file://') === 0) :
            $localPath = substr($remoteUrl, 7);
            if (!is_file($localPath)) :
                $this->message->logMessage('[ERROR]', "Remote image does not exist: $remoteUrl");
                return false;
            endif;
            $localSize = $this->fileSize($localPath);
            if ($localSize < 1 || $localSize > self::MAX_REMOTE_IMAGE_BYTES) :
                $this->message->logMessage('[ERROR]', "Remote image has an invalid size: $remoteUrl");
                return false;
            endif;
            $image = @file_get_contents($localPath);
            return $image === false ? false : $image;
        endif;

        $userAgent = UserAgent::buildFromConfig($this->appConfig, null, $this->message);
        $body = '';
        $tooLarge = false;
        $curl = curl_init($remoteUrl);
        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: image/webp,*/*;q=0.8']);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            $body .= $chunk;
            if (strlen($body) > self::MAX_REMOTE_IMAGE_BYTES) :
                $tooLarge = true;
                return 0;
            endif;
            return strlen($chunk);
        });
        $success = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($success === false || $tooLarge || $httpCode < 200 || $httpCode >= 300 || $body === '') :
            $reason = $tooLarge ? 'response exceeds the size limit' : "HTTP $httpCode: $error";
            $this->message->logMessage('[ERROR]', "Unable to download remote WebP $remoteUrl ($reason)");
            return false;
        endif;

        return $body;
    }

    private function fileSize(string $path): int
    {
        $size = @filesize($path);
        return $size === false ? 0 : $size;
    }

    /** @return array<string, mixed> */
    private function migrationResult(
        string $status,
        string $source,
        string $jpegPath,
        string $webpPath,
        int $jpegBytes,
        ?string $databaseField = null
    ): array {
        return [
            'status' => $status,
            'source' => $source,
            'database_field' => $databaseField,
            'webp_path' => $webpPath,
            'jpeg_path' => $jpegPath,
            'jpeg_bytes' => $jpegBytes,
            'webp_bytes' => 0,
            'jpeg_deleted' => false,
            'cleanup_failed' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function migrationFailure(string $status): array
    {
        return $this->migrationResult($status, '', '', '', 0);
    }

    /** @return array<string, mixed> */
    private function migrationNotRequired(): array
    {
        return $this->migrationResult('not_required', '', '', '', 0);
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
