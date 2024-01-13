<?php
/* Version:     2.0
    Date:       13/01/24
    Name:       scryfall_sets.php
    Purpose:    Import/update Scryfall sets data
    Notes:      {none} 
        
    1.0         Release 1
 * 
 *  2.0         13/01/24
 *              Move to PHPMailer for email output
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message;

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// How old to overwrite
$max_fileage = 23 * 3600;
$time = time();

// Scryfall rulings cards URL
$url = "https://api.scryfall.com/sets";

// Bulk file store point
$file_location = $ImgLocation.'json/sets.json';

//Check image location
if (!file_exists($ImgLocation."seticons")):
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Creating new directory {$ImgLocation}/seticons",$logfile);
    mkdir($ImgLocation."seticons");
endif;

// Set counts
$total_count = 0;

$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: Download URI: $url",$logfile);
if (file_exists($file_location)):
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i',$fileage);
    if (time()-$fileage > $max_fileage):
        $download = 2;
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: File old ($file_location, $file_date), downloading",$logfile);  
    else:
        $download = 0;
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: File fresh ($file_location, $file_date), skipping download",$logfile);    
    endif;
else:
    $download = 1;
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: No file at ($file_location), downloading: $url",$logfile);
endif;
if($download > 0):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: ($file_location), downloading: $url",$logfile);
    $setsreturn = downloadbulk($url,$file_location);
endif;
$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: Local file: $file_location",$logfile);

$data = Items::fromFile($ImgLocation.'json/sets.json', ['decoder' => new ExtJsonDecoder(true)]);
if ($result = $db->query('TRUNCATE TABLE sets')):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Sets API: sets table cleared",$logfile);
else:
    trigger_error('[ERROR] scryfall_sets.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
endif;
foreach($data AS $key => $value):
    if($key == 'data'):
        foreach($value as $key2 => $value2):
            $id = $value2["id"];
            $code = $value2["code"];
            $name = $value2["name"];
            $api_uri = $value2["uri"];
            $scryfall_uri = $value2["scryfall_uri"];
            $search_uri = $value2["search_uri"];
            $release_date = $value2["released_at"];
            $set_type = $value2['set_type'];
            $card_count = $value2["card_count"];
            if(isset($value2["parent_set_code"])):
                $parent_set_code = $value2["parent_set_code"];
            else:
                $parent_set_code = $value2["code"];
            endif;
            $nonfoil_only = $value2["nonfoil_only"];
            $foil_only = $value2["foil_only"];
            $icon_svg_uri = $value2['icon_svg_uri'];
            $stmt = $db->prepare("INSERT INTO 
                                    `sets`
                                        (id, code, name, api_uri, scryfall_uri, search_uri, release_date, set_type, card_count, parent_set_code, nonfoil_only, foil_only, icon_svg_uri)
                                    VALUES 
                                        (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($stmt === false):
                trigger_error('[ERROR] scryfall_sets: Preparing SQL: ' . $db->error, E_USER_ERROR);
            endif;
            $stmt->bind_param("ssssssssisiis", 
                    $id,
                    $code,
                    $name,
                    $api_uri,
                    $scryfall_uri,
                    $search_uri,
                    $release_date,
                    $set_type,
                    $card_count,
                    $parent_set_code,
                    $nonfoil_only,
                    $foil_only,
                    $icon_svg_uri);
            if ($stmt === false):
                trigger_error('[ERROR] scryfall_sets: Binding parameters: ' . $db->error, E_USER_ERROR);
            endif;
            if (!$stmt->execute()):
                trigger_error("[ERROR] scryfall_sets: Writing new ruling details: " . $db->error, E_USER_ERROR);
            else:
                $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Add sets $total_count - no error returned ",$logfile);
                $total_count = $total_count + 1;
            endif;
            $stmt->close();
            //$seticon = $ImgLocation."seticons/".$parent_set_code.".svg";
            $seticon = $ImgLocation."seticons/".$code.".svg";
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Set icon for '$code' to be $seticon from $icon_svg_uri?$time",$logfile);
            if(!file_exists($seticon)):
                $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Icon not at $seticon",$logfile);
                downloadbulk($icon_svg_uri."?".$time,$seticon);
            endif;
            $seticon = $icon_svg_uri = '';
        endforeach;
    endif;
endforeach;
$msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"$total_count bulk sets completed",$logfile);

// Email results
$subject = "MTG sets update completed"; 
$body = "Total sets: $total_count";
$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
$msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Mail result is '$mailresult'",$logfile);