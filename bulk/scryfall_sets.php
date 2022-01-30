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
$url = "https://api.scryfall.com/sets";

// Bulk file store point
$file_location = $ImgLocation.'json/sets.json';

// Set counts
$total_count = 0;

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: Download URI: $url",$logfile);
if (file_exists($file_location)):
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i',$fileage);
    if (time()-$fileage > $max_fileage):
        $download = 2;
        $obj = new Message;
        $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: File old ($file_location, $file_date), downloading",$logfile);  
    else:
        $download = 0;
        $obj = new Message;
        $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: File fresh ($file_location, $file_date), skipping download",$logfile);    
    endif;
else:
    $download = 1;
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: No file at ($file_location), downloading: $url",$logfile);
endif;
if($download > 0):
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: ($file_location), downloading: $url",$logfile);
    $setsreturn = downloadbulk($url,$file_location);
endif;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall sets API: Local file: $file_location",$logfile);

$data = Items::fromFile($ImgLocation.'json/sets.json', ['decoder' => new ExtJsonDecoder(true)]);

$obj = new Message;
$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"$total_count bulk sets completed",$logfile);
$from = "From: $serveremail\r\nReturn-path: $serveremail"; 
$subject = "MTG sets update completed"; 
$message = "Total: $total_count";
mail($adminemail, $subject, $message, $from); 