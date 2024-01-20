<?php
/*  Version:    2.0
    Date:       13/01/24
    Name:       scryfall_rulings.php
    Purpose:    Import/update Scryfall rulings data
    Notes:      {none} 
        
    1.0         Downloads Scryfall rulings file, wipes and writes rulings_scry table
 * 
 *  2.0         13/01/24
 *              Move to PHPMailer for email output
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message($logfile);

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

$msg->logMessage('[NOTICE]',"Scryfall Rulings API: fetching $url");
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_rulings = json_decode($curlresult,true);
if(isset($scryfall_rulings["type"]) AND $scryfall_rulings["type"] === "rulings"):
    $rulings_uri = $scryfall_rulings["download_uri"];
endif;
$msg->logMessage('[NOTICE]',"Scryfall Rulings API: Download URI: $rulings_uri");

if (file_exists($file_location)):
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i',$fileage);
    if (time()-$fileage > $max_fileage):
        $download = 2;
        $msg->logMessage('[NOTICE]',"Scryfall Rulings API: File old ($file_location, $file_date), downloading $rulings_uri");
    else:
        $download = 0;
        $msg->logMessage('[NOTICE]',"Scryfall Rulings API: File fresh ($file_location, $file_date), skipping download");    
    endif;
else:
    $download = 1;
    $msg->logMessage('[NOTICE]',"Scryfall Rulings API: No file at ($file_location), downloading: $url");
endif;
if($download > 0):
    $msg->logMessage('[NOTICE]',"Scryfall Rulings API: downloading: $url");
    $rulingreturn = downloadbulk($rulings_uri,$file_location);
endif;
$msg->logMessage('[NOTICE]',"Scryfall Rulings API: Local file: $file_location");

$data = Items::fromFile($ImgLocation.'json/rulings.json', ['decoder' => new ExtJsonDecoder(true)]);
if ($result = $db->query('TRUNCATE TABLE rulings_scry')):
    $msg->logMessage('[NOTICE]',"Scryfall Rulings API: Old rulings cleared");
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
        $msg->logMessage('[DEBUG]',"Add ruling $total_count - no error returned ");
        $total_count = $total_count + 1;
    endif;
    $stmt->close();
endforeach;
$msg->logMessage('[NOTICE]',"$total_count bulk rulings completed");

// Email results
$subject = "MTG rulings update completed"; 
$body = "Total rulings: $total_count";
$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
$msg->logMessage('[DEBUG]',"Mail result is '$mailresult'");