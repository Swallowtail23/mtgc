<?php
/* Version:     1.0
    Date:       16/11/23
    Name:       imagemanager.class.php
    Purpose:    Local image management class
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0
                Initial version
 * 
 * 
    Example usage
        $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
        $result = $imageManager->getImage($setcode, $cardid, $ImgLocation, $layout, $two_card_detail_sections);
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class ImageManager
{
    private $db;
    private $logfile;
    private $serveremail;
    private $adminemail;
    private $message;

    public function __construct($db, $logfile, $serveremail, $adminemail)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->serveremail = $serveremail;
        $this->adminemail = $adminemail;
        $this->message = new Message();
    }

    public function getImage($setcode, $cardid, $ImgLocation, $layout, $two_card_detail_sections)
    {
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": called for $setcode, $cardid, $ImgLocation, $layout", $this->logfile);

        $localfile = $ImgLocation . $setcode . '/' . $cardid . '.jpg';
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": File should be at $localfile", $this->logfile);

        if (in_array($layout, $two_card_detail_sections)):
            $localfile_b = $ImgLocation . $setcode . '/' . $cardid . '_b.jpg';
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Back file should be at $localfile_b", $this->logfile);
        endif;

        // Front face
        if (!file_exists($localfile)):
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $localfile missing, running get image function", $this->logfile);

            $sql = "SELECT image_uri, layout, f1_image_uri FROM cards_scry WHERE id like ? LIMIT 1";
            $result = $this->db->execute_query($sql,[$cardid]);

            if ($result === false):
                trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL error: " . $this->db->error, E_USER_ERROR);
            else:
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Query $sql successful", $this->logfile);

                $coderow = $result->fetch_array(MYSQLI_ASSOC);
                $imageurl = '';

                if (isset($coderow['image_uri']) AND !is_null($coderow['image_uri'])):
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Standard card, {$coderow['image_uri']}", $this->logfile);
                    $imageurl = strtolower($coderow['image_uri']);
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Looking on scryfall.com ($cardid) for image to use as $localfile", $this->logfile);

                elseif (isset($coderow['f1_image_uri']) AND !is_null($coderow['f1_image_uri'])):
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Flip card, {$coderow['f1_image_uri']}", $this->logfile);
                    $imageurl = strtolower($coderow['f1_image_uri']);
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Looking on scryfall.com ($cardid) for images to use as $localfile", $this->logfile);
                endif;

                if (strpos($imageurl, '.jpg?') !== false):
                    $imageurl = substr($imageurl, 0, (strpos($imageurl, ".jpg?") + 5)) . "1";
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Imageurl is $imageurl", $this->logfile);
                endif;

                if ((checkRemoteFile($imageurl) == false) OR ($imageurl === '')):
                    $imageurl = '';
                    $from = "From: $this->serveremail\r\nReturn-path: $this->serveremail";
                    $subject = "Invalid image from Scryfall API";
                    $message = "$imageurl for card $cardid does not exist - check database entry against API, has it been deleted?";
                    mail($this->adminemail, $subject, $message, $from);
                    $frontimg = 'error';
                else:
                    $options = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                    $context = stream_context_create($options);
                    $image = file_get_contents($imageurl, false, $context);

                    if (!file_exists($ImgLocation . $setcode)):
                        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Creating new directory $setcode", $this->logfile);
                        mkdir($ImgLocation . $setcode);
                    endif;

                    file_put_contents($localfile, $image);
                    $relativepath = strpos($localfile, 'cardimg');
                    $frontimg = substr($localfile, $relativepath);
                endif;
            endif;
        else:
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": File exists already at $localfile", $this->logfile);
            $relativepath = strpos($localfile, 'cardimg');
            $frontimg = substr($localfile, $relativepath);
        endif;

        $imageurl = array('front' => $frontimg,
                          'back' => '');

        // Back face
        if (isset($localfile_b)):
            if (!file_exists($localfile_b)):
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $localfile_b missing, running get image function", $this->logfile);

                $sql = "SELECT layout, f2_image_uri FROM cards_scry WHERE id like ? LIMIT 1";
                $result2 = $this->db->execute_query($sql,[$cardid]);

                if ($result2 === false):
                    trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL error: " . $this->db->error, E_USER_ERROR);
                else:
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Query $sql successful", $this->logfile);

                    $coderow2 = $result2->fetch_array(MYSQLI_ASSOC);
                    $imageurl_2 = '';

                    if (isset($coderow2['f2_image_uri']) AND !is_null($coderow2['f2_image_uri'])):
                        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Flip card back, {$coderow2['f2_image_uri']}", $this->logfile);
                        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Looking on scryfall.com ($cardid) for images to use as $localfile_b", $this->logfile);
                        $imageurl_2 = strtolower($coderow2['f2_image_uri']);
                    endif;

                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Flip card back image, {$coderow2['f2_image_uri']}", $this->logfile);
                    $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Looking on scryfall.com ($cardid) for image to use as $localfile_b", $this->logfile);

                    if (strpos($imageurl_2, '.jpg?') !== false):
                        $imageurl_2 = substr($imageurl_2, 0, (strpos($imageurl_2, ".jpg?") + 5)) . "1";
                        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Imageurl_2 is $imageurl_2", $this->logfile);
                    endif;

                    if ($imageurl_2 === ''):
                        $backimg = 'empty';
                    elseif (checkRemoteFile($imageurl_2) == false):
                        $backimg = 'error';
                    else:
                        $options = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                        $context = stream_context_create($options);
                        $image2 = file_get_contents($imageurl_2, false, $context);

                        if (!file_exists($ImgLocation . $setcode)):
                            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Creating new directory $setcode", $this->logfile);
                            mkdir($ImgLocation . $setcode);
                        endif;

                        file_put_contents($localfile_b, $image2);
                        $relativepath_2 = strpos($localfile_b, 'cardimg');
                        $backimg = substr($localfile_b, $relativepath_2);
                    endif;
                endif;
            elseif (file_exists($localfile_b)):
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": File exists already at $localfile_b", $this->logfile);
                $relativepath_2 = strpos($localfile_b, 'cardimg');
                $backimg = substr($localfile_b, $relativepath_2);
            endif;

            $imageurl = array('front' => $frontimg,
                              'back' => $backimg);
        endif;
        return $imageurl;
    }
    
    public function diffImage($url, $localFilePath) 
    {
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Comparing $url with local file $localFilePath", $this->logfile);
        
        // Get headers for the online image
        $onlineHeaders = get_headers($url, 1);

        if ($onlineHeaders === false):
            // Failed to retrieve headers for the online image
            return false;
        endif;

        // Get the "Content-Length" header to check file size
        if (isset($onlineHeaders['Content-Length'])):
            $onlineFileSize = $onlineHeaders['Content-Length'];
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $url size is $onlineFileSize", $this->logfile);
        else:
            $onlineFileSize = 0;
        endif;

        // Get the "Last-Modified" header to check the modification date
        if (isset($onlineHeaders['Last-Modified'])):
            $onlineLastModified = strtotime($onlineHeaders['Last-Modified']);
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $url mod time is $onlineLastModified", $this->logfile);
        else:
            $onlineLastModified = 0;
        endif;

        // Get the local file size
        $localFileSize = filesize($localFilePath);
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $localFilePath size is $localFileSize", $this->logfile);

        // Get the local file modification date
        $localLastModified = filemtime($localFilePath);
        $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": $localFilePath mod time is $localLastModified", $this->logfile);

        // Compare file sizes and modification dates
        if ($onlineFileSize !== $localFileSize OR $onlineLastModified !== $localLastModified):
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Result:- files are different", $this->logfile);
            return true;
        else:
            $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Result:- files are same", $this->logfile);
            return false;
        endif;
    }

    function refreshImage($cardid)
    {
        global $ImgLocation, $two_card_detail_sections;
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Refresh image called for $cardid",$this->logfile);

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        $sql = "SELECT id,setcode,layout FROM cards_scry WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($sql,[$cardid]);
        if ($result === false):
            restore_error_handler();
            return 'failure'; 
        else:
            $imagebackdelete = $imagedelete = '';
            $row = $result->fetch_assoc();
            $imagefunction = $this->getImage($row['setcode'],$cardid,$ImgLocation,$row['layout'],$two_card_detail_sections); //$ImgLocation is set in ini
            if($imagefunction['front'] != 'error'):
                $imagename = substr($imagefunction['front'], strrpos($imagefunction['front'], '/') + 1);
                $imageurl = $ImgLocation.$row['setcode']."/".$imagename;
                try {
                    if (!unlink($imageurl)):
                        $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Failed to unlink $imageurl",$this->logfile);
                        throw new Exception('Failed to unlink image');
                    endif;
                    $imagedelete = 'success';
                } catch (Exception $e) {
                    $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Failed to unlink $imageurl",$this->logfile);
                    $imagedelete = 'failure';
                    
                } finally {
                    restore_error_handler();
                }
            endif;
            if($imagefunction['back'] != '' AND $imagefunction['back'] != 'error' AND $imagefunction['back'] != 'empty'):
                $imagebackname = substr($imagefunction['back'], strrpos($imagefunction['back'], '/') + 1);
                $imagebackurl = $ImgLocation.$row['setcode']."/".$imagebackname;
                try {
                    if (!unlink($imagebackurl)):
                        $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Failed to unlink $imagebackurl",$this->logfile);
                        throw new Exception('Failed to unlink back image');
                    endif;
                    $imagebackdelete = 'success';
                } catch (Exception $e) {
                    $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Failed to unlink $imagebackurl",$this->logfile);
                    $imagebackdelete = 'failure';
                    restore_error_handler();
                }
            endif;
        endif;
        //Refresh image
        if($imagebackdelete === 'failure' || $imagedelete === 'failure'):
            $from = "From: $this->serveremail\r\nReturn-path: $this->serveremail"; 
            $subject = "Image unlink failure";
            $message = "Failed image unlink: $imageurl. Front: $imagedelete; Back: $imagebackdelete";
            mail($this->adminemail, $subject, $message, $from);
            return 'failure'; 
        else:
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Re-fetching image for $cardid",$this->logfile);
            $imagefunction = $this->getImage($row['setcode'],$cardid,$ImgLocation,$row['layout'],$two_card_detail_sections); //$ImgLocation is set in ini
            return 'success';
        endif;
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}