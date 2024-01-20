<?php
/* Version:     2.1
    Date:       20/01/24
    Name:       scryfall_migrations.php
    Purpose:    Import/update Scryfall migrations/deletions data
    Notes:      {none} 
        
    1.0         First version
 * 
 *  2.0         13/01/24
 *              Move to PHPMailer for email output
 *  
 *  2.1         20/01/24
 *              Move to logMessage
*/

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message($logfile);

// URLs
$starturl = "https://api.scryfall.com/migrations";
$myURL = $ini_array['general']['URL'];

// Bulk file store point
$file_folder = $ImgLocation.'json/';

// Row counts
$total_count = 0;
$need_action = 0;
$deleted = 0;
$action_text = '';

// How old to overwrite
$max_fileage = 23 * 3600;

function getMigrationData($url,$file_location,$max_fileage,$pageNumber)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    $msg->logMessage('[DEBUG]',"Fetching Download URI: $url");
    if($pageNumber == 0):
        $page = $file_location.'migrations.json';
    else:
        $page = $file_location.'migrations'.$pageNumber.'.json';
    endif;
    
    if (file_exists($page)):
        $fileage = filemtime($page);
        $file_date = date('d-m-Y H:i',$fileage);
        if (time()-$fileage > $max_fileage):
            $download = 2;
            $msg->logMessage('[DEBUG]',"Scryfall migrations API: File old ($page, $file_date), downloading");  
        else:
            $download = 0;
            $msg->logMessage('[DEBUG]',"Scryfall migrations API: File fresh ($page, $file_date), skipping download");    
        endif;
    else:
        $download = 1;
        $msg->logMessage('[DEBUG]',"Scryfall migrations API: No file at ($page), downloading: $url");
    endif;
    
    if($download > 0):
        $msg->logMessage('[DEBUG]',"Scryfall migrations API: ($page), downloading: $url");
        $setsreturn = downloadbulk($url,$page);
    endif;
    return $page;
}
function checkMigrationDataForMore($file)
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    $data = Items::fromFile($file, ['decoder' => new ExtJsonDecoder(true)]);
    $next_page = 'none';
    foreach($data AS $key => $value):
        if($key == 'has_more' AND $value == 'true'):
            $more = TRUE;
            $msg->logMessage('[DEBUG]',"Further pages available");
        endif;
        if(isset($more) AND $more == TRUE AND $key == 'next_page'):
            $next_page = $value;
        endif;
    endforeach;
    return $next_page;
}
function clearDBMigrations()
{
    global $db, $logfile;
    $msg = new Message($logfile);
    
    if ($result = $db->query('TRUNCATE TABLE migrations')):
        $msg->logMessage('[NOTICE]',"Scryfall migrations API: migrations table cleared");
    else:
        trigger_error('[ERROR] scryfall_migrations.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
    endif;
}
function getRowCount($file)
{
    $data = Items::fromFile($file, ['decoder' => new ExtJsonDecoder(true)]);
    $count = 0;
    foreach($data AS $key => $value):
        if($key == 'data'):
            foreach($value as $key2 => $value2):
                foreach($value2 as $key3 => $value3):
                    if($key3 == 'id'):          
                        $count = $count + 1;
                    endif;
                endforeach;
            endforeach;
        endif;
    endforeach;
    return $count;
}

function safeDeleteCheck ($id)
{
    global $db, $logfile;
    $safeScore = null;
    $msg = new Message($logfile);

    //Find if it's in any decks
    $userResultArray = $collectionResultArray = $resultArray = array();
    $sql = "SELECT deckname, username FROM decks
        LEFT JOIN users ON decks.owner = users.usernumber
        LEFT JOIN deckcards ON decks.decknumber = deckcards.decknumber
        WHERE deckcards.cardnumber = ?";
    $params = [$id];
    $result = $db->execute_query($sql,$params);
    if ($result === false):
        $safeScore = 10000;
    else:
        $deckMatches = $result->num_rows;
        $msg->logMessage('[DEBUG]',"Matches in decks for '$id': $deckMatches");
        if($deckMatches > 0):
            $safeScore = 1;
        else:
            $safeScore = 0;
        endif;
    endif;

    //Get user list
    $sql = "SELECT usernumber,username FROM users";
    $result = $db->execute_query($sql);
    if ($result === false):
        $safeScore = 20000;
    else:
        $users = [];
        while ($row = $result->fetch_assoc()):
            $users[] = ['usernumber' => $row['usernumber'], 'username' => $row['username']];
        endwhile;
    endif;

    //Find if it's in any user collections
    foreach($users as $user):
        $table = $user['usernumber']."collection";
        $sql = "SELECT SUM(COALESCE(`$table`.`normal`, 0) + COALESCE(`$table`.`foil`, 0) + COALESCE(`$table`.`etched`, 0)) AS total FROM `$table` WHERE id = ?";
        $params = [$id];
        $result = $db->execute_query($sql,$params);
        if ($result === false):
            $safeScore = $safeScore + 100000;
        else:
            while ($row = $result->fetch_assoc()):
                if($row['total'] !== NULL AND $row['total'] != 0):
                    $msg->logMessage('[DEBUG]',"Found one!: User: {$user['username']}, ID: $id: Total: {$row['total']}");
                    $safeScore = $safeScore + 5;
                endif;
            endwhile;
        endif;
    endforeach;
    return $safeScore;
}

// Script logic runs from here
$page = 0;
$file = getMigrationData($starturl,$file_folder,$max_fileage,$page);
$result_files = array();
$result_files[$page] = $file;
$moreurl = checkMigrationDataForMore($file);
while($moreurl != 'none'):
    $page = $page + 1;
    $file = getMigrationData($moreurl,$file_folder,$max_fileage,$page);
    $result_files[$page] = $file;
    $moreurl = checkMigrationDataForMore($file);
endwhile;
$results = $page + 1;
$total_rows = 0;
foreach($result_files as $data):
    $rows = getRowCount($data);
    $total_rows = $total_rows + $rows;
endforeach;
if($total_rows > 0):
    clearDBMigrations();
endif;

foreach($result_files as $data):
    $decodedjson = Items::fromFile($data, ['decoder' => new ExtJsonDecoder(true)]);
    foreach($decodedjson AS $key => $value):
        if($key == 'data'):
            foreach($value as $key2 => $value2):
                $id = $value2["id"];
                $performed_at = $value2["performed_at"];
                $object = $value2["object"];
                $migration_strategy = $value2["migration_strategy"];
                $uri = $value2["uri"];
                $old_scryfall_id = $value2["old_scryfall_id"];
                if(isset($value2["new_scryfall_id"])):
                    $new_scryfall_id = $value2["new_scryfall_id"];
                else:
                    $new_scryfall_id = NULL;
                endif;
                if(isset($value2["note"])):
                    $note = $value2["note"];
                else:
                    $note = NULL;
                endif;
                if(isset($value2["metadata"])):
                    if(isset($value2["metadata"]["id"])):
                        $metadata_id = $value2["metadata"]["id"];
                    else:
                        $metadata_id = NULL;
                    endif;
                    if(isset($value2["metadata"]["lang"])):
                        $metadata_lang = $value2["metadata"]["lang"];
                    else:
                        $metadata_lang = NULL;
                    endif;
                    if(isset($value2["metadata"]["name"])):
                        $metadata_name = $value2["metadata"]["name"];
                    else:
                        $metadata_name = NULL;
                    endif;
                    if(isset($value2["metadata"]["set_code"])):
                        $metadata_set_code = $value2["metadata"]["set_code"];
                    else:
                        $metadata_set_code = NULL;
                    endif;
                    if(isset($value2["metadata"]["oracle_id"])):
                        $metadata_oracle_id = $value2["metadata"]["oracle_id"];
                    else:
                        $metadata_oracle_id = NULL;
                    endif;
                    if(isset($value2["metadata"]["collector_number"])):
                        $metadata_collector_number = $value2["metadata"]["collector_number"];
                    else:
                        $metadata_collector_number = NULL;
                    endif;
                else:
                    $metadata_id = $metadata_lang = $metadata_name = $metadata_set_code = $metadata_oracle_id = $metadata_collector_number = NULL;
                endif;
                $stmt = $db->execute_query('SELECT id from cards_scry WHERE id = ?',[$old_scryfall_id]);
                if ($stmt === false):
                    trigger_error('[ERROR] scryfall_migrations: Preparing SQL: ' . $db->error, E_USER_ERROR);
                else:
                    $deleteCheck = safeDeleteCheck($old_scryfall_id);
                    if ($stmt->num_rows > 0 && $deleteCheck > 10000):
                        //In db, but safety check failed
                        $db_match = 1;
                        $need_action = $need_action + 1;
                        $msg->logMessage('[DEBUG]',"$old_scryfall_id exists in existing data and safety check failed, $need_action to be actioned");
                        $action_text = $action_text."Old ID: $myURL/carddetail.php?id=$old_scryfall_id\n Migration strategy: $migration_strategy\n New ID (if applicable): $myURL/carddetail.php?id=$new_scryfall_id\n Note: $note (Safety check failed)\n\n";
                    elseif ($stmt->num_rows > 0 && $deleteCheck > 0):
                        $db_match = 1;
                        $need_action = $need_action + 1;
                        $msg->logMessage('[NOTICE]',"$old_scryfall_id exists in existing data but not safe to delete, $need_action to be actioned");
                        $action_text = $action_text."Old ID: $myURL/carddetail.php?id=$old_scryfall_id\n Migration strategy: $migration_strategy\n New ID (if applicable): $myURL/carddetail.php?id=$new_scryfall_id\n Note: $note (Not safe to delete)\n\n";
                    elseif ($stmt->num_rows > 0 && $deleteCheck === 0):
                        //In db, but ok to remove
                        $msg->logMessage('[NOTICE]',"$old_scryfall_id exists in existing data, but not in any decks or collections - can be deleted");
                        $deleted = $deleted + 1;
                        //Delete query here
                        $sql = "DELETE FROM cards_scry WHERE id = ?";
                        $params = [$old_scryfall_id];
                        $deleteResult = $db->execute_query($sql,$params);
                        if ($deleteResult !== false):
                            $msg->logMessage('[NOTICE]',"Deleted $old_scryfall_id from cards_scry");
                            $db_match = 0;
                        else:
                            $db_match = 1;
                        endif;
                    else:
                        //Not in db
                        $msg->logMessage('[DEBUG]',"$old_scryfall_id does not exist in existing data");
                        $db_match = 0;
                    endif;
                endif;
                $stmt = $db->prepare("INSERT INTO 
                                        `migrations`
                                            (id, performed_at, object, migration_strategy, uri, old_scryfall_id, new_scryfall_id, note, metadata_id, metadata_lang, metadata_name, metadata_set_code, metadata_oracle_id, metadata_collector_number, db_match)
                                        VALUES 
                                            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                if ($stmt === false):
                    trigger_error('[ERROR] scryfall_migrations: Preparing SQL: ' . $db->error, E_USER_ERROR);
                endif;
                $stmt->bind_param("sssssssssssssss", 
                        $id,
                        $performed_at,
                        $object,
                        $migration_strategy,
                        $uri,
                        $old_scryfall_id,
                        $new_scryfall_id,
                        $note,
                        $metadata_id,
                        $metadata_lang,
                        $metadata_name,
                        $metadata_set_code,
                        $metadata_oracle_id,
                        $metadata_collector_number,
                        $db_match);
                if ($stmt === false):
                    trigger_error('[ERROR] scryfall_migrations: Binding parameters: ' . $db->error, E_USER_ERROR);
                endif;
                if (!$stmt->execute()):
                    trigger_error("[ERROR] scryfall_migrations: Writing new migration details: " . $db->error, E_USER_ERROR);
                else:
                    $msg->logMessage('[DEBUG]',"Add migrations $total_count - no error returned");
                    $total_count = $total_count + 1;
                endif;
                $stmt->close();
            endforeach;
        endif;
    endforeach;
endforeach;

$msg->logMessage('[NOTICE]',"$total_count bulk migrations completed, $need_action actions needed");

// Email results
$subject = "MTG migrations update completed";
$body = "Total: $total_count \nNeed action: $need_action \n$action_text";
$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
$msg->logMessage('[DEBUG]',"Mail result is '$mailresult'");