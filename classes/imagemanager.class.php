<?php

/*
Version:     1.1
Date:        25/11/25
Name:        imagemanager.class.php
Purpose:     Local image management class.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 16/11/23 Initial version
    1.1 25/11/25 Standard tidy-up
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

        $localfile = $imgLocation . $setcode . '/' . $cardId . '.jpg';
        $this->message->logMessage('[DEBUG]', "File should be at $localfile");

        if (in_array($layout, $twoCardDetailSections)) :
            $localFileB = $imgLocation . $setcode . '/' . $cardId . '_b.jpg';
            $this->message->logMessage('[DEBUG]', "Back file should be at $localFileB");
        endif;

        // Front face
        if (!file_exists($localfile)) :
            $this->message->logMessage('[DEBUG]', "$localfile missing, running get image function");

            $sql = "SELECT image_uri, layout, f1_image_uri FROM cards_scry WHERE id like ? LIMIT 1";
            $result = $this->db->execute_query($sql, [$cardId]);

            if ($result === false) :
                trigger_error(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL error: " . $this->db->error,
                    E_USER_ERROR
                );
            else :
                $this->message->logMessage('[DEBUG]', "Query $sql successful");

                $codeRow = $result->fetch_array(MYSQLI_ASSOC);
                $imageUrl = '';

                if (isset($codeRow['image_uri']) and !is_null($codeRow['image_uri'])) :
                    $this->message->logMessage('[DEBUG]', "Standard card, {$codeRow['image_uri']}");
                    $imageUrl = strtolower($codeRow['image_uri']);
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Looking on scryfall.com ($cardId) for image to use as $localfile"
                    );
                elseif (isset($codeRow['f1_image_uri']) and !is_null($codeRow['f1_image_uri'])) :
                    $this->message->logMessage('[DEBUG]', "Flip card, {$codeRow['f1_image_uri']}");
                    $imageUrl = strtolower($codeRow['f1_image_uri']);
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Looking on scryfall.com ($cardId) for images to use as $localfile"
                    );
                endif;

                if (strpos($imageUrl, '.jpg?') !== false) :
                    $imageUrl = substr($imageUrl, 0, (strpos($imageUrl, ".jpg?") + 5)) . "?t=" . time();
                    $this->message->logMessage('[DEBUG]', "Imageurl is $imageUrl");
                endif;

                if ((checkRemoteFile($imageUrl) == false) or ($imageUrl === '')) :
                    $imageUrl = '';
                    $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
                    $subject = "Invalid image from Scryfall API";
                    $message = "$imageUrl for card $cardId does not exist - check database entry against API, "
                        . "has it been deleted?";
                    mail($this->adminEmail, $subject, $message, $from);
                    $frontImg = 'error';
                else :
                    $options = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                    $context = stream_context_create($options);
                    $image = file_get_contents($imageUrl, false, $context);

                    if (!file_exists($imgLocation . $setcode)) :
                        $this->message->logMessage('[DEBUG]', "Creating new directory $setcode");
                        mkdir($imgLocation . $setcode);
                    endif;

                    file_put_contents($localfile, $image);
                    $relativePath = strpos($localfile, 'cardimg');
                    $frontImg = substr($localfile, $relativePath);
                endif;
            endif;
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

                $sql = "SELECT layout, f2_image_uri FROM cards_scry WHERE id like ? LIMIT 1";
                $result2 = $this->db->execute_query($sql, [$cardId]);

                if ($result2 === false) :
                    trigger_error(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL error: " . $this->db->error,
                        E_USER_ERROR
                    );
                else :
                    $this->message->logMessage('[DEBUG]', "Query $sql successful");

                    $codeRow2 = $result2->fetch_array(MYSQLI_ASSOC);
                    $imageUrl2 = '';

                    if (isset($codeRow2['f2_image_uri']) and !is_null($codeRow2['f2_image_uri'])) :
                        $this->message->logMessage('[DEBUG]', "Flip card back, {$codeRow2['f2_image_uri']}");
                        $this->message->logMessage(
                            '[DEBUG]',
                            "Looking on scryfall.com ($cardId) for images to use as $localFileB"
                        );
                        $imageUrl2 = strtolower($codeRow2['f2_image_uri']);
                    endif;

                    $this->message->logMessage('[DEBUG]', "Flip card back image, {$codeRow2['f2_image_uri']}");
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Looking on scryfall.com ($cardId) for image to use as $localFileB"
                    );

                    if (strpos($imageUrl2, '.jpg?') !== false) :
                        $imageUrl2 = substr($imageUrl2, 0, (strpos($imageUrl2, ".jpg?") + 5)) . "?t=" . time();
                        $this->message->logMessage('[DEBUG]', "Imageurl_2 is $imageUrl2");
                    endif;

                    if ($imageUrl2 === '') :
                        $backImg = 'empty';
                    elseif (checkRemoteFile($imageUrl2) == false) :
                        $backImg = 'error';
                    else :
                        $options = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                        $context = stream_context_create($options);
                        $image2 = file_get_contents($imageUrl2, false, $context);

                        if (!file_exists($imgLocation . $setcode)) :
                            $this->message->logMessage('[DEBUG]', "Creating new directory $setcode");
                            mkdir($imgLocation . $setcode);
                        endif;

                        file_put_contents($localFileB, $image2);
                        $relativePath2 = strpos($localFileB, 'cardimg');
                        $backImg = substr($localFileB, $relativePath2);
                    endif;
                endif;
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

    public function diffImage($url, $localFilePath)
    {
        $this->message->logMessage('[DEBUG]', "Comparing $url with local file $localFilePath");

        // Get headers for the online image
        $onlineHeaders = get_headers($url, 1);

        if ($onlineHeaders === false) :
            // Failed to retrieve headers for the online image
            return false;
        endif;

        // Get the "Content-Length" header to check file size
        if (isset($onlineHeaders['Content-Length'])) :
            $onlineFileSize = $onlineHeaders['Content-Length'];
            $this->message->logMessage('[DEBUG]', "$url size is $onlineFileSize");
        else :
            $onlineFileSize = 0;
        endif;

        // Get the "Last-Modified" header to check the modification date
        if (isset($onlineHeaders['Last-Modified'])) :
            $onlineLastModified = strtotime($onlineHeaders['Last-Modified']);
            $this->message->logMessage('[DEBUG]', "$url mod time is $onlineLastModified");
        else :
            $onlineLastModified = 0;
        endif;

        // Get the local file size
        $localFileSize = filesize($localFilePath);
        $this->message->logMessage('[DEBUG]', "$localFilePath size is $localFileSize");

        // Get the local file modification date
        $localLastModified = filemtime($localFilePath);
        $this->message->logMessage('[DEBUG]', "$localFilePath mod time is $localLastModified");

        // Compare file sizes and modification dates
        if ($onlineFileSize !== $localFileSize or $onlineLastModified !== $localLastModified) :
            $this->message->logMessage('[DEBUG]', "Result:- files are different");
            return true;
        else :
            $this->message->logMessage('[DEBUG]', "Result:- files are same");
            return false;
        endif;
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
            $imagefunction = $this->getImage(
                $row['setcode'],
                $cardId,
                $imgLocation,
                $row['layout'],
                $twoCardDetailSections
            );
            if ($imagefunction['front'] != 'error') :
                $imagename = substr($imagefunction['front'], strrpos($imagefunction['front'], '/') + 1);
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
                $imagefunction['back'] != ''
                and $imagefunction['back'] != 'error'
                and $imagefunction['back'] != 'empty'
            ) :
                $imagebackname = substr($imagefunction['back'], strrpos($imagefunction['back'], '/') + 1);
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
            mail($this->adminEmail, $subject, $message, $from);
            return 'failure';
        else :
            $this->message->logMessage('[DEBUG]', "Re-fetching image for $cardId");
            // $imgLocation is set in ini
            $imagefunction = $this->getImage(
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
}
// phpcs:enable
