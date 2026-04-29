<?php

/*
Version:     1.24
Date:        29/04/26
Name:        ImageManager.php
Purpose:     Local image management class.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

/*
Example usage:
    $obj = new ImageManager($db, $appConfig, $gameRules);
    $result = $obj->getImage($setcode, $cardId, $layout);
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
    // Skip remote checks for images newer than this (in seconds)
    private const IMAGE_MAX_AGE = 604800; // 7 days

    /**
    * @var \mysqli|object
    */
    private $db;
    private string $adminEmail;
    private Message $message;
    private AppConfig $appConfig;
    private GameRules $gameRules;

    /**
    * @param \mysqli|object $db
    */
    public function __construct($db, AppConfig $appConfig, GameRules $gameRules)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->gameRules = $gameRules;
        $this->adminEmail = (string) $this->appConfig->email('adminEmail', '');
        $this->message = new Message($this->appConfig);
    }

    /**
    * @return array{front: string, back: string}
    */
    public function getImage(string $setcode, string $cardId, string $layout, bool $allowFetch = true): array
    {
        $allowFetchLabel = ($allowFetch) ? 'true' : 'false';
        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $twoCardDetailSections = $this->gameRules->get('twoCardDetailSections', []);
        if (!is_array($twoCardDetailSections)) :
            $twoCardDetailSections = [];
        endif;
        $this->message->logMessage(
            '[DEBUG]',
            "Called for $setcode, $cardId, $imgLocation, $layout (fetch $allowFetchLabel)"
        );

        $cardImages = $this->getCardImageUris($cardId);
        $localfile = $imgLocation . $setcode . '/' . $cardId . '.jpg';
        $backImg = '';
        $this->message->logMessage('[DEBUG]', "File should be at $localfile");

        if (in_array($layout, $twoCardDetailSections)) :
            $localFileB = $imgLocation . $setcode . '/' . $cardId . '_b.jpg';
            $this->message->logMessage('[DEBUG]', "Back file should be at $localFileB");
        endif;

        // Front face
        if (!$this->isReadable($localfile)) :
            if ($this->fileExists($localfile)) :
                $this->message->logMessage('[DEBUG]', "File exists but is not readable at $localfile");
            endif;
            if ($allowFetch) :
                $this->message->logMessage('[DEBUG]', "$localfile missing, running get image function");
                $frontImg = $this->fetchAndStoreImage($cardImages['front'], $imgLocation, $setcode, $localfile);
            else :
                $this->message->logMessage('[DEBUG]', "$localfile missing, using placeholder");
                $frontImg = '/images/back.jpg';
            endif;
        else :
            $this->message->logMessage('[DEBUG]', "File readable already at $localfile");
            $relativePath = strpos($localfile, 'cardimg');
            $frontImg = substr($localfile, $relativePath);
        endif;

        $imageUrl = [
            'front' => $frontImg,
            'back' => '',
        ];

        // Back face
        if (isset($localFileB)) :
            if (!$this->isReadable($localFileB)) :
                if ($this->fileExists($localFileB)) :
                    $this->message->logMessage('[DEBUG]', "File exists but is not readable at $localFileB");
                endif;
                if ($allowFetch) :
                    $this->message->logMessage('[DEBUG]', "$localFileB missing, running get image function");
                    $backImg = $this->fetchAndStoreImage($cardImages['back'], $imgLocation, $setcode, $localFileB);
                else :
                    $this->message->logMessage('[DEBUG]', "$localFileB missing, using placeholder");
                    $backImg = '/images/back.jpg';
                endif;
            elseif ($this->isReadable($localFileB)) :
                $this->message->logMessage('[DEBUG]', "File readable already at $localFileB");
                $relativePath2 = strpos($localFileB, 'cardimg');
                $backImg = substr($localFileB, $relativePath2);
            endif;

            $imageUrl = array('front' => $frontImg,
                              'back' => $backImg);
        endif;
        return $imageUrl;
    }

    public function diffImage(string $remoteUrl, string $localFilePath): bool
    {
        $this->message->logMessage('[DEBUG]', "Comparing $remoteUrl with $localFilePath");

        $headers = @get_headers($remoteUrl, 1);
        if ($headers === false || stripos($headers[0], '200') === false) :
            $this->message->logMessage('[ERROR]', "Unable to fetch headers for $remoteUrl");
            return false;
        endif;

        $remoteSize = isset($headers['Content-Length']) ? (int) $headers['Content-Length'] : null;
        if ($remoteSize === null) :
            $this->message->logMessage('[DEBUG]', "No comparable headers on $remoteUrl");
            return false;
        endif;

        $localSize = filesize($localFilePath);
        $sizeDiffers = ($remoteSize !== $localSize);

        if ($sizeDiffers) :
            $this->message->logMessage(
                '[NOTICE]',
                "Image differs (size? yes)"
            );
            return true;
        endif;

        $this->message->logMessage('[DEBUG]', "Image matches remote headers");
        @touch($localFilePath); // bump mtime to avoid immediate rechecks
        return false;
    }

    /**
    * @return array{success: bool, front: string, back: string}
    */
    public function refreshImage(string $cardId): array
    {
        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $twoCardDetailSections = $this->gameRules->get('twoCardDetailSections', []);
        if (!is_array($twoCardDetailSections)) :
            $twoCardDetailSections = [];
        endif;
        $this->message->logMessage('[DEBUG]', "Refresh image called for $cardId");

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $sql = "SELECT id,setcode,layout FROM cards_scry WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$cardId]);
        if ($result === false) :
            restore_error_handler();
            return array(
                'success' => false,
                'front' => '',
                'back' => ''
            );
        else :
            $imagebackdelete = $imagedelete = '';
            $imageUrl = '';
            $row = $result->fetch_assoc();
            // $imgLocation is set in ini
            $imageFunction = $this->getImage(
                $row['setcode'],
                $cardId,
                $row['layout']
            );
            if ($imageFunction['front'] != 'error') :
                $imagename = substr($imageFunction['front'], strrpos($imageFunction['front'], '/') + 1);
                $imageUrl = $imgLocation . $row['setcode'] . "/" . $imagename;
                try {
                    if (!unlink($imageUrl)) :
                        $this->message->logMessage('[ERROR]', "Failed to unlink $imageUrl");
                        mtgError(E_USER_ERROR, 'Failed to unlink image', __FILE__, __LINE__, $this->appConfig);
                    endif;
                    $imagedelete = 'success';
                } catch (\Exception $e) {
                    $this->message->logMessage('[ERROR]', "Failed to unlink $imageUrl");
                    $imagedelete = 'failure';
                } finally {
                    restore_error_handler();
                }
            endif;
            if (
                $imageFunction['back'] != ''
                and $imageFunction['back'] != 'error'
                and $imageFunction['back'] != 'empty'
            ) :
                $imagebackname = substr($imageFunction['back'], strrpos($imageFunction['back'], '/') + 1);
                $imagebackurl = $imgLocation . $row['setcode'] . "/" . $imagebackname;
                try {
                    if (!unlink($imagebackurl)) :
                        $this->message->logMessage('[ERROR]', "Failed to unlink $imagebackurl");
                        mtgError(E_USER_ERROR, 'Failed to unlink back image', __FILE__, __LINE__, $this->appConfig);
                    endif;
                    $imagebackdelete = 'success';
                } catch (\Exception $e) {
                    $this->message->logMessage('[ERROR]', "Failed to unlink $imagebackurl");
                    $imagebackdelete = 'failure';
                    restore_error_handler();
                }
            endif;
        endif;
        //Refresh image
        if ($imagebackdelete === 'failure' || $imagedelete === 'failure') :
            $subject = "Image unlink failure";
            $message = "Failed image unlink: $imageUrl. Front: $imagedelete; Back: $imagebackdelete";
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                $mail = new MyPHPMailer(true, $this->appConfig);
                $mail->sendEmail($this->adminEmail, false, $subject, $message);
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; image unlink failure alert not sent for "
                    . "Front: $imagedelete; Back: $imagebackdelete"
                );
            endif;
            return array(
                'success' => false,
                'front' => '',
                'back' => ''
            );
        else :
            $this->message->logMessage('[DEBUG]', "Re-fetching image for $cardId");
            // $imgLocation is set in ini
            $imageFunction = $this->getImage(
                $row['setcode'],
                $cardId,
                $row['layout']
            );
            $this->message->logMessage(
                '[DEBUG]',
                "Refresh image complete for $cardId. Front: {$imageFunction['front']}; Back: {$imageFunction['back']}"
            );
            return array(
                'success' => true,
                'front' => $imageFunction['front'],
                'back' => $imageFunction['back']
            );
        endif;
    }


    /**
    * @return array{front: string, front_changed: bool, back: string, back_changed: bool}
    */
    public function checkAndRefreshImage(string $cardId): array
    {
        $imgLocation = (string) $this->appConfig->general('imageBaseDir', '');
        $twoCardDetailSections = $this->gameRules->get('twoCardDetailSections', []);
        if (!is_array($twoCardDetailSections)) :
            $twoCardDetailSections = [];
        endif;

        $cardData = $this->getCardImageUris($cardId);
        $setcode = $cardData['setcode'];

        $frontPath = $imgLocation . $setcode . '/' . $cardId . '.jpg';
        $backPath = $imgLocation . $setcode . '/' . $cardId . '_b.jpg';

        $frontResult = $this->processImageFaceRefresh($cardData['front'], $frontPath, $imgLocation, $setcode);
        $backResult = array('path' => '', 'changed' => false);

        if (in_array($cardData['layout'], $twoCardDetailSections)) :
            $backResult = $this->processImageFaceRefresh($cardData['back'], $backPath, $imgLocation, $setcode);
        endif;

        return array(
            'front' => $frontResult['path'],
            'front_changed' => $frontResult['changed'],
            'back' => $backResult['path'],
            'back_changed' => $backResult['changed'],
        );
    }

    /**
    * @return array{front: string, back: string, setcode: string, layout: string}
    */
    private function getCardImageUris(string $cardId): array
    {
        $sql = "SELECT image_uri, f1_image_uri, f2_image_uri, setcode, layout FROM cards_scry WHERE id like ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$cardId]);

        if ($result === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL error: " . $this->db->error
            );
        endif;

        $row = $result->fetch_array(MYSQLI_ASSOC);

        $front = '';
        $back = '';

        if (isset($row['image_uri']) && !is_null($row['image_uri'])) :
            $front = strtolower($row['image_uri']);
        elseif (isset($row['f1_image_uri']) && !is_null($row['f1_image_uri'])) :
            $front = strtolower($row['f1_image_uri']);
        endif;

        if (isset($row['f2_image_uri']) && !is_null($row['f2_image_uri'])) :
            $back = strtolower($row['f2_image_uri']);
        endif;

        $front = $this->normaliseImageUrl($front);
        $back = $this->normaliseImageUrl($back);

        return array(
            'front' => $front,
            'back' => $back,
            'setcode' => $row['setcode'],
            'layout' => $row['layout']
        );
    }

    private function normaliseImageUrl(string $url): string
    {
        if ($url === '') :
            return '';
        endif;
        if (strpos($url, '.jpg?') !== false) :
            return substr($url, 0, (strpos($url, ".jpg?") + 5));
        endif;
        return $url;
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

        if (RemoteFileChecker::exists($remoteUrl, $this->appConfig, $this->message) == false) :
            $subject = "Invalid image from Scryfall API";
            $message = "$remoteUrl does not exist - check database entry against API, has it been deleted?";
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                $mail = new MyPHPMailer(true, $this->appConfig);
                $mail->sendEmail($this->adminEmail, false, $subject, $message);
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; image unlink failure alert not sent for $remoteUrl"
                );
            endif;
            return 'error';
        endif;

        $userAgent = UserAgent::buildFromConfig($this->appConfig, null, $this->message);
        $this->message->logMessage('[DEBUG]', "Image fetch user agent set to $userAgent");
        $options = array('http' => array('user_agent' => $userAgent));
        $context = stream_context_create($options);
        $image = file_get_contents($remoteUrl, false, $context);

        if (!file_exists($imgLocation . $setcode)) :
            $this->message->logMessage('[DEBUG]', "Creating new directory $setcode");
            mkdir($imgLocation . $setcode);
        endif;

        file_put_contents($destination, $image);
        $relativePath = strpos($destination, 'cardimg');
        return substr($destination, $relativePath);
    }

    private function processImageFace(
        string $remoteUrl,
        string $localPath,
        string $imgLocation,
        string $setcode
    ): string {
        $relativePath = strpos($localPath, 'cardimg');
        $currentPath = substr($localPath, $relativePath);

        if (!file_exists($localPath)) :
            return $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $localPath);
        endif;

        $age = time() - filemtime($localPath);
        if ($age < self::IMAGE_MAX_AGE) :
            return $currentPath;
        endif;

        if ($remoteUrl !== '' && $this->diffImage($remoteUrl, $localPath)) :
            $this->message->logMessage('[DEBUG]', "Refreshing local image $localPath from $remoteUrl");
            return $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $localPath);
        endif;

        return $currentPath;
    }

    /**
    * @return array{path: string, changed: bool}
    */
    private function processImageFaceRefresh(
        string $remoteUrl,
        string $localPath,
        string $imgLocation,
        string $setcode
    ): array {
        $relativePath = strpos($localPath, 'cardimg');
        $currentPath = substr($localPath, $relativePath);

        if (!file_exists($localPath)) :
            $this->message->logMessage('[DEBUG]', "Image missing, fetching $localPath");
            $path = $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $localPath);
            return array('path' => $path, 'changed' => true);
        endif;

        $age = time() - filemtime($localPath);
        if ($age < self::IMAGE_MAX_AGE) :
            $this->message->logMessage('[DEBUG]', "Image fresh, skipping refresh for $localPath");
            return array('path' => $currentPath, 'changed' => false);
        endif;

        if ($remoteUrl !== '' && $this->diffImage($remoteUrl, $localPath)) :
            $this->message->logMessage('[DEBUG]', "Refreshing local image $localPath from $remoteUrl");
            $path = $this->fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $localPath);
            return array('path' => $path, 'changed' => true);
        endif;

        $this->message->logMessage('[DEBUG]', "Image unchanged for $localPath");
        return array('path' => $currentPath, 'changed' => false);
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
// phpcs:enable
