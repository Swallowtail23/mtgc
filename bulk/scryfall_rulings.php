<?php
/* Version:     1.0
    Date:       22/01/22
    Name:       scryfall_rulings.php
    Purpose:    Import/update Scryfall rulings data
    Notes:      {none} 
        
    1.0         Downloads Scryfall rulings file, wipes and writes rulings_scry table
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message;

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// How old to overwrite
$max_fileage = 23 * 3600;

// Scryfall rulings cards URL
$url = "https://api.scryfall.com/bulk-data/rulings";

// Bulk file store point
$file_location = $ImgLocation.'json/rulings.json';

// Set counts
$total_count = 0;

$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: fetching $url",$logfile);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_rulings = json_decode($curlresult,true);
if(isset($scryfall_rulings["type"]) AND $scryfall_rulings["type"] === "rulings"):
    $rulings_uri = $scryfall_rulings["download_uri"];
endif;
$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: Download URI: $rulings_uri",$logfile);

if (file_exists($file_location)):
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i',$fileage);
    if (time()-$fileage > $max_fileage):
        $download = 2;
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: File old ($file_location, $file_date), downloading $rulings_uri",$logfile);
    else:
        $download = 0;
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: File fresh ($file_location, $file_date), skipping download",$logfile);    
    endif;
else:
    $download = 1;
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: No file at ($file_location), downloading: $url",$logfile);
endif;
if($download > 0):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: downloading: $url",$logfile);
    $rulingreturn = downloadbulk($rulings_uri,$file_location);
endif;
$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: Local file: $file_location",$logfile);

$data = Items::fromFile($ImgLocation.'json/rulings.json', ['decoder' => new ExtJsonDecoder(true)]);
if ($result = $db->query('TRUNCATE TABLE rulings_scry')):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Rulings API: Old rulings cleared",$logfile);
else:
    trigger_error('[ERROR] scryfall_rulings.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
endif;
foreach($data AS $key => $value):
    $oracle_id = $value["oracle_id"];
    $source = $value["source"];
    $published = $value["published_at"];
    $comment = $value["comment"];
    $stmt = $db->prepare("INSERT INTO 
                            `rulings_scry`
                                (oracle_id, source, published_at, comment)
                            VALUES 
                                (?,?,?,?)");
    if ($stmt === false):
        trigger_error('[ERROR] scryfall_rulings: Preparing SQL: ' . $db->error, E_USER_ERROR);
    endif;
    $stmt->bind_param("ssss", 
            $oracle_id,
            $source,
            $published,
            $comment);
    if ($stmt === false):
        trigger_error('[ERROR] scryfall_rulings: Binding parameters: ' . $db->error, E_USER_ERROR);
    endif;
    if (!$stmt->execute()):
        trigger_error("[ERROR] scryfall_rulings: Writing new ruling details: " . $db->error, E_USER_ERROR);
    else:
        $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Add ruling $total_count - no error returned ",$logfile);
        $total_count = $total_count + 1;
    endif;
    $stmt->close();
endforeach;
$msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"$total_count bulk rulings completed",$logfile);
$from = "From: $serveremail\r\nReturn-path: $serveremail"; 
$subject = "MTG rulings update completed"; 
$message = "Total: $total_count";
mail($adminemail, $subject, $message, $from); 