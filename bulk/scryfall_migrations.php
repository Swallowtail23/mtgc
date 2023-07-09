<?php
/* Version:     1.0
    Date:       09/07/23
    Name:       scryfall_migrations.php
    Purpose:    Import/update Scryfall migrations/deletions data
    Notes:      {none} 
        
    1.0         First version
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// Scryfall migrations cards URL
$starturl = "https://api.scryfall.com/migrations";

// Bulk file store point
$file_folder = $ImgLocation.'json/';

// Row counts
$total_count = 0;

// How old to overwrite
$max_fileage = 23 * 3600;

function getMigrationData($url,$file_location,$max_fileage,$pageNumber)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": fetching Download URI: $url",$logfile);
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
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall migrations API: File old ($page, $file_date), downloading",$logfile);  
        else:
            $download = 0;
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall migrations API: File fresh ($page, $file_date), skipping download",$logfile);    
        endif;
    else:
        $download = 1;
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall migrations API: No file at ($page), downloading: $url",$logfile);
    endif;
    
    if($download > 0):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall migrations API: ($page), downloading: $url",$logfile);
        $setsreturn = downloadbulk($url,$page);
    endif;
    return $page;
}
function checkMigrationDataForMore($file)
{
    global $db, $logfile;
    $data = Items::fromFile($file, ['decoder' => new ExtJsonDecoder(true)]);
    $next_page = 'none';
    foreach($data AS $key => $value):
        if($key == 'has_more' AND $value == 'true'):
            $more = TRUE;
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Further pages available",$logfile);
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
    if ($result = $db->query('TRUNCATE TABLE migrations')):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall migrations API: migrations table cleared",$logfile);
    else:
        trigger_error('[ERROR] scryfall_migrations.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
    endif;
}
function getRowCount($file)
{
    global $logfile;
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
    $need_action = 0;
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
                endif;
                if ($stmt->num_rows > 0):
                    $db_match = 1;
                    $need_action = $need_action + 1;
                else:
                    $db_match = 0;
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
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Add migrations $total_count - no error returned ",$logfile);
                    $total_count = $total_count + 1;
                endif;
                $stmt->close();
            endforeach;
        endif;
    endforeach;
endforeach;

$obj = new Message;
$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"$total_count bulk migrations completed",$logfile);
$from = "From: $serveremail\r\nReturn-path: $serveremail"; 
$subject = "MTG migrations update completed"; 
$message = "Total: $total_count \nNeed action: $need_action";
mail($adminemail, $subject, $message, $from); 