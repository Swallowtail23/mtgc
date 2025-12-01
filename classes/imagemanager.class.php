<?php

/*
Version:     1.3
Date:        29/11/25
Name:        imagemanager.class.php
Purpose:     Local image management class.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 16/11/23 Initial version
    1.1 25/11/25 Standard tidy-up
    1.2 29/11/25 Add remote/local diff check and refresh
    1.3 29/11/25 Move refresh to async path; add image check endpoint support
*/

/*
Example usage:
    $obj = new ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $result = $obj->getImage($setcode, $cardId, $imgLocation, $layout, $twoCardDetailSections);
*/

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, PSR1.Files.SideEffects.FoundWithSymbols
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class ImageManager
{
    // Skip remote checks for images newer than this (in seconds)
    private const IMAGE_MAX_AGE = 604800; // 7 days

    private $db;
    private $logfile;
    private $serverEmail;
    private $adminEmail;
    private $message;

    public function __construct($db, $logfile, $serverEmail, $adminEmail)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->serverEmail = $serverEmail;
        $this->adminEmail = $adminEmail;
        $this->message = new Message($this->logfile);
    }

    public function getImage($setcode, $cardId, $imgLocation, $layout, $twoCardDetailSections)
    {
        $this->message->logMessage('[DEBUG]', "Called for $setcode, $cardId, $imgLocation, $layout");

        $cardImages = $this->getCardImageUris($cardId);
        $localfile = $imgLocation . $setcode . '/' . $cardId . '.jpg';
        $this->message->logMessage('[DEBUG]', "File should be at $localfile");

        if (in_array($layout, $twoCardDetailSections)) :
            $localFileB = $imgLocation . $setcode . '/' . $cardId . '_b.jpg';
            $this->message->logMessage('[DEBUG]', "Back file should be at $localFileB");
        endif;

        // Front face
        if (!file_exists($localfile)) :
            $this->message->logMessage('[DEBUG]', "$localfile missing, running get image function");
            $frontImg = $this->fetchAndStoreImage($cardImages['front'], $imgLocation, $setcode, $localfile);
        else :
            $this->message->logMessage('[DEBUG]', "File exists already at $localfile");
            $relativePath = strpos($localfile, 'cardimg');
            $frontImg = substr($localfile, $relativePath);
        endif;

        $imageUrl = [
            'front' => $frontImg,
            'back' => '',
        ];

        // Back face
        if (isset($localFileB)) :
            if (!file_exists($localFileB)) :
                $this->message->logMessage('[DEBUG]', "$localFileB missing, running get image function");
                $backImg = $this->fetchAndStoreImage($cardImages['back'], $imgLocation, $setcode, $localFileB);
            elseif (file_exists($localFileB)) :
                $this->message->logMessage('[DEBUG]', "File exists already at $localFileB");
                $relativePath2 = strpos($localFileB, 'cardimg');
                $backImg = substr($localFileB, $relativePath2);
            endif;

            $imageUrl = array('front' => $frontImg,
                              'back' => $backImg);
        endif;
        return $imageUrl;
    }

    public function diffImage($remoteUrl, $localFilePath)
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

    public function refreshImage($cardId)
    {
        global $imgLocation, $twoCardDetailSections;
        $this->message->logMessage('[DEBUG]', "Refresh image called for $cardId");

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $sql = "SELECT id,setcode,layout FROM cards_scry WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$cardId]);
        if ($result === false) :
            restore_error_handler();
            return 'failure';
        else :
            $imagebackdelete = $imagedelete = '';
            $row = $result->fetch_assoc();
            // $imgLocation is set in ini
            $imageFunction = $this->getImage(
                $row['setcode'],
                $cardId,
                $imgLocation,
                $row['layout'],
                $twoCardDetailSections
            );
            if ($imageFunction['front'] != 'error') :
                $imagename = substr($imageFunction['front'], strrpos($imageFunction['front'], '/') + 1);
                $imageUrl = $imgLocation . $row['setcode'] . "/" . $imagename;
                try {
                    if (!unlink($imageUrl)) :
                        $this->message->logMessage('[ERROR]', "Failed to unlink $imageUrl");
                        throw new Exception('Failed to unlink image');
                    endif;
                    $imagedelete = 'success';
                } catch (Exception $e) {
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
                        throw new Exception('Failed to unlink back image');
                    endif;
                    $imagebackdelete = 'success';
                } catch (Exception $e) {
                    $this->message->logMessage('[ERROR]', "Failed to unlink $imagebackurl");
                    $imagebackdelete = 'failure';
                    restore_error_handler();
                }
            endif;
        endif;
        //Refresh image
        if ($imagebackdelete === 'failure' || $imagedelete === 'failure') :
            $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
            $subject = "Image unlink failure";
            $message = "Failed image unlink: $imageUrl. Front: $imagedelete; Back: $imagebackdelete";
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                mail($this->adminEmail, $subject, $message, $from);
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; missing image alert not sent for $remoteUrl"
                );
            endif;
            return 'failure';
        else :
            $this->message->logMessage('[DEBUG]', "Re-fetching image for $cardId");
            // $imgLocation is set in ini
            $imageFunction = $this->getImage(
                $row['setcode'],
                $cardId,
                $imgLocation,
                $row['layout'],
                $twoCardDetailSections
            );
            return 'success';
        endif;
    }

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }

    public function checkAndRefreshImage($cardId)
    {
        global $imgLocation, $twoCardDetailSections;

        $cardData = $this->getCardImageUris($cardId);
        $setcode = $cardData['setcode'];

        $frontPath = $imgLocation . $setcode . '/' . $cardId . '.jpg';
        $backPath = $imgLocation . $setcode . '/' . $cardId . '_b.jpg';

        $frontResult = $this->processImageFace($cardData['front'], $frontPath, $imgLocation, $setcode);
        $backResult = '';

        if (in_array($cardData['layout'], $twoCardDetailSections)) :
            $backResult = $this->processImageFace($cardData['back'], $backPath, $imgLocation, $setcode);
        endif;

        return array(
            'front' => $frontResult,
            'back' => $backResult,
        );
    }

    private function getCardImageUris($cardId)
    {
        $sql = "SELECT image_uri, f1_image_uri, f2_image_uri, setcode, layout FROM cards_scry WHERE id like ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$cardId]);

        if ($result === false) :
            trigger_error(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL error: " . $this->db->error,
                E_USER_ERROR
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

    private function normaliseImageUrl($url)
    {
        if ($url === '') :
            return '';
        endif;
        if (strpos($url, '.jpg?') !== false) :
            return substr($url, 0, (strpos($url, ".jpg?") + 5));
        endif;
        return $url;
    }

    private function fetchAndStoreImage($remoteUrl, $imgLocation, $setcode, $destination)
    {
        if ($remoteUrl === '') :
            return 'empty';
        endif;

        if (checkRemoteFile($remoteUrl) == false) :
            $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
            $subject = "Invalid image from Scryfall API";
            $message = "$remoteUrl does not exist - check database entry against API, has it been deleted?";
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                mail($this->adminEmail, $subject, $message, $from);
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; image unlink failure alert not sent for $imagebackurl"
                );
            endif;
            return 'error';
        endif;

        $options = array('http' => array('user_agent' => 'MtGCollection/1.0'));
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

    private function processImageFace($remoteUrl, $localPath, $imgLocation, $setcode)
    {
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
}
// phpcs:enable
