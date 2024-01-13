<?php
/* Version:     8.0
    Date:       13/01/24
    Name:       scryfall_bulk.php
    Purpose:    Import/update Scryfall bulk data
    Notes:      {none} 
        
    1.0         Downloads Scryfall bulk file, checks, adds, updates cards_scry table
 
    2.0         Cope with up to 7 card parts
 
 *  3.0
 *              Add Arena legalities
 *  4.0
 *              Add parameter for refresh of file ("new")
 *              Add handling for zero-byte download
 *  5.0
 *              Added handling for etched cards
 *  6.0
 *              Retrieve and store promo type info
 * 
 *  7.0         02/01/24
 *              Major rewrite, moving logic to functions and adding ability to process All Cards as well as Default Cards
 * 
 *  8.0         13/01/24
 *              Move email function to use phpmailer
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');

$msg = new Message;

// Get and interpret parameter 1

/// Call without parameters does a 'default' file update only
/// Call with 'all' gets the all cards file
/// Call with 'refresh' gets fresh copies of BOTH files

if(isset($argv[1])):
    if($argv[1] == "all"):
        $type = "all";
    elseif($argv[1] == "refresh"):
        $type = "refresh";
    else:
        $type = "default";
    endif;
else:
    $type = "default";
endif;

// Get info on required files to download and their local locations
$bulkInfo = getBulkInfo($type);

if ($bulkInfo !== FALSE):
    if ($type === "refresh"):
        $bulk_uri_all = $bulkInfo['bulkUrlAll'];
        $bulk_uri_default = $bulkInfo['bulkUrlDefault'];
        $file_location_all = $bulkInfo['fileLocationAll'];
        $file_location_default = $bulkInfo['fileLocationDefault'];
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URIs: $bulk_uri_all / $bulk_uri_default; File locations: $file_location_all / $file_location_default",$logfile);
        $max_fileage = 0;
        $get_all = getBulkJson($bulk_uri_all, $file_location_all, $max_fileage);
        $get_default = getBulkJson($bulk_uri_default, $file_location_default, $max_fileage);
        if ($get_all === FALSE || $get_default === FALSE):
            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: getBulkJson returned error for $bulk_uri_all / $bulk_uri_default",$logfile);
            exit;
        else:
            scryfallImport($file_location_all,'all');
            scryfallImport($file_location_default,'default');
        endif;
    else:
        $bulk_uri = $bulkInfo['bulkUrl'];
        $file_location = $bulkInfo['fileLocation'];
        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: $bulk_uri; File location: $file_location",$logfile);
        $max_fileage = 23 * 3600;
        $get_json = getBulkJson($bulk_uri, $file_location, $max_fileage);
        if ($get_json === FALSE):
            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: getBulkJson returned error for $bulk_uri",$logfile);
            exit;
        else:
            if ($file_location === $ImgLocation.'json/bulk.json'):
                $type = 'default';
            elseif ($file_location === $ImgLocation.'json/bulk_all.json'):
                $type = 'all';
            endif;
            
            // Email results
            $bulkResultMessage = scryfallImport($file_location,$type);
            $subject = "MTG bulk update completed ($type)";
            $mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
            $mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $bulkResultMessage);
            $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Mail result is '$mailresult'",$logfile);
        endif;
    endif;
else:
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: bulk_info function failed to return usable results",$logfile);
    exit;
endif;