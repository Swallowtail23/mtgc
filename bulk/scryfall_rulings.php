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
require ('../includes/functions_new.php');

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// How old to overwrite
$max_fileage = 23 * 3600;

// Scryfall rulings cards URL
$url = "https://api.scryfall.com/bulk-data/rulings";

// Bulk file store point
$file_location = $ImgLocation.'json/rulings.json';

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Rulings API: fetching $url",$logfile);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_rulings = json_decode($curlresult,true);
if(isset($scryfall_rulings["type"]) AND $scryfall_rulings["type"] === "rulings"):
    $rulings_uri = $scryfall_rulings["download_uri"];
endif;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Rulings API: Download URI: $rulings_uri",$logfile);

if (time()-filemtime($file_location) > $max_fileage):
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File old, downloading: $bulk_uri",$logfile);
    $bulkreturn = downloadbulk($bulk_uri,$file_location);
else:
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File fresh (".$file_location."), skipping download",$logfile);    
endif;
$rulingreturn = downloadbulk($rulings_uri,$file_location);
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Rulings API: Local file: $file_location",$logfile);

$data = Items::fromFile($ImgLocation.'json/rulings.json', ['decoder' => new ExtJsonDecoder(true)]);
if ($result = $db->query('TRUNCATE TABLE rulings_scry')):
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Rulings API: Old rulings cleared",$logfile);
else:
    trigger_error('[ERROR] scryfall_bulk.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
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
        trigger_error('[ERROR] scryfall_bulk: Preparing SQL: ' . $db->error, E_USER_ERROR);
    endif;
    $stmt->bind_param("ssss", 
            $oracle_id,
            $source,
            $published,
            $comment);
    if ($stmt === false):
        trigger_error('[ERROR] scryfall_bulk: Binding parameters: ' . $db->error, E_USER_ERROR);
    endif;
    if (!$stmt->execute()):
        trigger_error("[ERROR] scryfall_bulk: Writing new ruling details: " . $db->error, E_USER_ERROR);
    else:
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Add ruling - no error returned",$logfile);
    endif;
    $stmt->close();
endforeach;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Bulk Rulings completed",$logfile);