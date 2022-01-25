<?php
/* Version:     1.0
    Date:       22/01/22
    Name:       scryfall_bulk.php
    Purpose:    Import/update Scryfall bulk data
    Notes:      {none} 
        
    1.0         Downloads Scryfall bulk file, checks, adds, updates cards_scry table
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// Lists
$langs_to_skip = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la'];
$layouts_to_skip = ['double_faced_token','token','emblem'];
$layouts_flips = ['transform','split','reversible_card','flip','meld','modal_dfc'];

// How old to overwrite
$stale = 23 * 3600;       // 23 hours for record age before replacing
$max_fileage = 23 * 3600; // 23 hours for file age before downloading new one
$now = new DateTime();
$today_date = $now->format('d');
$dates_to_full_update = [1,9,17,25];
// Scryfall bulk cards URL
$url = "https://api.scryfall.com/bulk-data/default-cards";

// Bulk file store point
$file_location = $ImgLocation.'json/bulk.json';

// Set counts
$count_inc = $count_skip = $count_add = $count_delete = $count_update = $count_price_update = $total_count = $count_nothing = 0;

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: fetching $url",$logfile);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_bulk = json_decode($curlresult,true);
if(isset($scryfall_bulk["type"]) AND $scryfall_bulk["type"] === "default_cards"):
    $bulk_uri = $scryfall_bulk["download_uri"];
endif;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Bulk API: Download URI: $bulk_uri",$logfile);

if (time()-filemtime($file_location) > $max_fileage):
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Bulk API: File old, downloading: $bulk_uri",$logfile);
    $bulkreturn = downloadbulk($bulk_uri,$file_location);
else:
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Bulk API: File fresh (".$file_location."), skipping download",$logfile);    
endif;

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": scryfall Bulk API: Local file: $file_location",$logfile);

$data = Items::fromFile($file_location, ['decoder' => new ExtJsonDecoder(true)]);
foreach($data AS $key => $value):
    $total_count = $total_count + 1;
    $id = $value["id"];
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, Record $id: $total_count",$logfile);
    $multi_1 = $multi_2 = $name_1 = $name_2 = $manacost_1 = $manacost_2 = $power_1 = $power_2 = null;
    $toughness_1 = $toughness_2 = $loyalty_1 = $loyalty_2 = $type_1 = $type_2 = $ability_1 = null;
    $ability_2 = $colour_1 = $colour_2 = $artist_1 = $artist_2 = $image_1 = $image_2 = null;
    $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
    $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
    $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
    $skip = 1; //skip by default
    //  Skips need to be specified in here
    /// Is it paper?
    foreach($value AS $key2 => $value2):
        if($key2 == 'games'):
            foreach($value2 as $game_type):
                if($game_type === 'paper'):
                    $skip = 0;
                endif;
            endforeach;
        endif;
    endforeach;
    if(in_array($value["lang"],$langs_to_skip) OR in_array($value["layout"],$layouts_to_skip)):
        $skip = 1;
    endif;
    // Actions on skip value
    if($skip === 1):
        $count_skip = $count_skip + 1;
        $stmt = $db->prepare("SELECT id FROM `cards_scry` WHERE id = ?");
        if ($stmt === false):
            trigger_error('[ERROR] scryfall_bulk.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $bind = $stmt->bind_param('s', $id);
        if ($bind === false):
            trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error("[ERROR] scryfall_bulk.php: Executing SQL" . $db->error, E_USER_ERROR);
        else:     
            if ($stmt->num_rows === 0):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, ignoring skip record not in db",$logfile);
            elseif($stmt->num_rows === 1):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, deleting skip record",$logfile);
                $stmt = $db->prepare("DELETE FROM `cards_scry` WHERE id = ?");
                if ($stmt === false):
                    trigger_error('[ERROR] scryfall_bulk.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
                endif;
                $bind = $stmt->bind_param('s', $id);
                if ($bind === false):
                    trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    trigger_error("[ERROR] scryfall_bulk.php: Executing SQL" . $db->error, E_USER_ERROR);
                else:
                    $count_delete = $count_delete + 1;
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, skip record deleted",$logfile);
                endif;
                $stmt->close();
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, skip record, deleting but double-up when id is unique ($id)",$logfile);
                $action = 'error';
            endif;
        endif;
        $stmt->close();
    elseif($skip === 0):
        $count_inc = $count_inc + 1;
        foreach($value AS $key2 => $value2):
            if($key2 == 'card_faces'):
                $face_loop = 1;
                foreach($value2 as $key3 => $value3):
                    if(isset($value3["name"])):
                        ${'name_'.$face_loop} = $value3["name"];
                    endif;
                    if(isset($value3["mana_cost"])):
                        ${'manacost_'.$face_loop} = $value3["mana_cost"];
                    endif;
                    if(isset($value3["power"])):
                        ${'power_'.$face_loop} = $value3["power"];
                    endif;
                    if(isset($value3["toughness"])):
                        ${'toughness_'.$face_loop} = $value3["toughness"];
                    endif;
                    if(isset($value3["loyalty"])):
                        ${'loyaltyt_'.$face_loop} = $value3["loyalty"];
                    endif;
                    if(isset($value3["type_line"])):
                        ${'type_'.$face_loop} = $value3["type_line"];
                    endif;
                    if(isset($value3["oracle_text"])):
                        ${'ability_'.$face_loop} = $value3["oracle_text"];
                    endif;
                    if(isset($value3["colors"])):
                        ${'colour_'.$face_loop} = json_encode($value3["colors"]);
                    endif;
                    if(isset($value3["artist"])):
                        ${'artist_'.$face_loop} = $value3["artist"];
                    endif;
                    if(isset($value3["image_uris"]["normal"])):
                        ${'image_'.$face_loop} = $value3["image_uris"]["normal"];
                    endif;
                    $face_loop = $face_loop + 1;
                endforeach;
            endif;
            if($key2 == 'all_parts'):
                $all_parts_loop = 1;
                foreach($value2 as $key4 => $value4):
                    if(isset($value4["id"])):
                        ${'id_p'.$all_parts_loop} = $value4["id"];
                    endif;
                    if(isset($value4["component"])):
                        ${'component_p'.$all_parts_loop} = $value4["component"];
                    endif;
                    if(isset($value4["name"])):
                        ${'name_p'.$all_parts_loop} = $value4["name"];
                    endif;
                    if(isset($value4["type_line"])):
                        ${'type_line_p'.$all_parts_loop} = $value4["type_line"];
                    endif;
                    if(isset($value4["uri"])):
                        ${'uri_p'.$all_parts_loop} = $value4["uri"];
                    endif;
                    $all_parts_loop = $all_parts_loop + 1;
                endforeach;
            endif;
            if($key2 == 'multiverse_ids'):
                $multiverse_loop = 1;
                foreach($value2 as $m_id):
                    ${'multi_'.$multiverse_loop} = $m_id;
                    $multiverse_loop = $multiverse_loop + 1;
                endforeach;
            endif;
        endforeach;

        // Check if it's already in db
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, Kept: $count_inc",$logfile);
        if($row = $db->select('id,updatetime','cards_scry',"WHERE id='$id'")):
            if ($row->num_rows === 0):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, no existing record, adding $id",$logfile);
                $action = 'add';
            elseif($row->num_rows === 1):
                $row = $row->fetch_assoc();
                $lastupdate = $row["updatetime"];
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, matched an existing record...",$logfile);
                if(time() - $lastupdate < $stale):
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, existing record fresh",$logfile);
                    $action = 'nothing';
                elseif(in_array($today_date,$dates_to_full_update)):
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, existing record stale, full update",$logfile);
                    $action = 'update';
                else:
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, existing record stale, price update",$logfile);
                    $action = 'price_update';                    
                endif;
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, double-ups when id is unique ($id)",$logfile);
                $action = 'error';
            endif;
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": scryfall Bulk API error",$logfile);
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": scryfall bulk API, action is: $action",$logfile);
        // Add or update, set values to send
        if(($action == 'add') OR ($action == 'update')):
            $time = time();
            if(isset($value["colors"])):
                $colors = json_encode($value["colors"]);
            endif;
            if(isset($value["games"])):
                $game_types = json_encode($value["games"]);
            endif; 
            if(isset($value["color_identity"])):
                $color_identity = json_encode($value["color_identity"]);
            endif;
            if(isset($value["keywords"])):
                $keywords = json_encode($value["keywords"]);
            endif;
            if(isset($value["produced_mana"])):
                $produced_mana = json_encode($value["produced_mana"]);
            endif;
            if(isset($value["collector_number"])):
                $coll_no = $value["collector_number"];
                if(isset($value["layout"]) AND $value["layout"] === 'meld'):
                    $coll_no = str_replace('a', '', $coll_no);
                    $coll_no = str_replace('b', '', $coll_no);
                endif;
                $coll_no = str_replace('-', '', $coll_no);
                $coll_no = str_replace('a', '1', $coll_no);
                $coll_no = str_replace('b', '2', $coll_no);
                $coll_no = str_replace('c', '3', $coll_no);
                $coll_no = str_replace('d', '4', $coll_no);
                $coll_no = str_replace('e', '5', $coll_no);
                $coll_no = str_replace('f', '6', $coll_no);
                $coll_no = str_replace('g', '7', $coll_no);
                $coll_no = str_replace('h', '8', $coll_no);
                $coll_no = str_replace('E', '', $coll_no);
                $coll_no = str_replace('★', '', $coll_no);
                $coll_no = str_replace('*', '', $coll_no);
                $coll_no = str_replace('†', '', $coll_no);
                $coll_no = str_replace('U', '', $coll_no);
                if(substr($coll_no, strlen($coll_no)-1) === 's'):
                    $coll_no = str_replace('s', '', $coll_no);
                    $coll_no = $coll_no + 2000;
                endif;
                if(substr($coll_no, strlen($coll_no)-1) === 'p'):
                    $coll_no = str_replace('p', '', $coll_no);
                endif;
                $number_int = (int) $coll_no;
            endif;
        elseif($action === 'price_update'):
            $time = time();
            $price = $value["prices"]["usd"];
            $foilprice = $value["prices"]["usd_foil"];
        endif;
        if($action == 'add'):
            $count_add = $count_add +1;
            $stmt = $db->prepare("INSERT INTO 
                                    `cards_scry`
                                    (id, oracle_id, tcgplayer_id, multiverse, multiverse2,
                                    name, lang, release_date, api_uri, scryfall_uri, 
                                    layout, image_uri, manacost, 
                                    cmc, type, ability, power, toughness, loyalty, color, color_identity, 
                                    keywords, generatedmana, legalitystandard, legalitypioneer, 
                                    legalitymodern, legalitylegacy, legalitypauper, legalityvintage, 
                                    legalitycommander, reserved, foil, nonfoil, oversized, promo, 
                                    set_id, game_types, setcode, set_name, number,
                                    number_import, rarity, flavor, backid, artist, price, price_foil, 
                                    gatherer_uri, updatetime,
                                    f1_name, f1_manacost, f1_power, f1_toughness, f1_loyalty, f1_type, f1_ability, f1_colour, f1_artist, f1_image_uri,
                                    f2_name, f2_manacost, f2_power, f2_toughness, f2_loyalty, f2_type, f2_ability, f2_colour, f2_artist, f2_image_uri,
                                    p1_id, p1_component, p1_name, p1_type_line, p1_uri,
                                    p2_id, p2_component, p2_name, p2_type_line, p2_uri,
                                    p3_id, p3_component, p3_name, p3_type_line, p3_uri
                                    )
                                VALUES 
                                    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($stmt === false):
                trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
            endif;
            $bind = $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss", 
                    $id, 
                    $value["oracle_id"],
                    $value["tcgplayer_id"],
                    $multi_1,
                    $multi_2,
                    $value["name"],
                    $value["lang"],
                    $value["released_at"],
                    $value["uri"],
                    $value["scryfall_uri"],
                    $value["layout"],
                    $value["image_uris"]["normal"],
                    $value["mana_cost"],
                    $value["cmc"],
                    $value["type_line"],
                    $value["oracle_text"],
                    $value["power"],
                    $value["toughness"],
                    $value["loyalty"],
                    $colors,
                    $color_identity,
                    $keywords,
                    $produced_mana,
                    $value["legalities"]["standard"],
                    $value["legalities"]["pioneer"],
                    $value["legalities"]["modern"],
                    $value["legalities"]["legacy"],
                    $value["legalities"]["pauper"],
                    $value["legalities"]["vintage"],
                    $value["legalities"]["commander"],
                    $value["reserved"],
                    $value["foil"],
                    $value["nonfoil"],
                    $value["oversized"],
                    $value["promo"],
                    $value["set_id"],
                    $game_types,
                    $value["set"],
                    $value["set_name"],
                    $number_int,
                    $value["collector_number"],
                    $value["rarity"],
                    $value["flavor_text"],
                    $value["card_back_id"],
                    $value["artist"],
                    $value["prices"]["usd"],
                    $value["prices"]["usd_foil"],
                    $value["related_uris"]["gatherer"],
                    $time,
                    $name_1,
                    $manacost_1,
                    $power_1,
                    $toughness_1,
                    $loyalty_1,
                    $type_1,
                    $ability_1,
                    $colour_1,
                    $artist_1,
                    $image_1,
                    $name_2,
                    $manacost_2,
                    $power_2,
                    $toughness_2,
                    $loyalty_2,
                    $type_2,
                    $ability_2,
                    $colour_2,
                    $artist_2,
                    $image_2,
                    $id_p1,
                    $component_p1,
                    $name_p1,
                    $type_line_p1,
                    $uri_p1,
                    $id_p2,
                    $component_p2,
                    $name_p2,
                    $type_line_p2,
                    $uri_p2,
                    $id_p3,
                    $component_p3,
                    $name_p3,
                    $type_line_p3,
                    $uri_p3
                    );
            if ($bind === false):
                trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                trigger_error("[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error, E_USER_ERROR);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Add card - no error returned",$logfile);
            endif;
            $stmt->close();
        elseif ($action == 'update'):
            $count_update = $count_update +1;
            $stmt = $db->prepare("UPDATE `cards_scry` SET
                                    oracle_id=?, tcgplayer_id=?, multiverse=?, multiverse2=?, 
                                    name=?, lang=?, release_date=?, api_uri=?, scryfall_uri=?, layout=?, 
                                    image_uri=?, manacost=?, cmc=?, type=?, ability=?, power=?, toughness=?, 
                                    loyalty=?, color=?, color_identity=?, keywords=?, generatedmana=?, 
                                    legalitystandard=?, legalitypioneer=?, legalitymodern=?, legalitylegacy=?, 
                                    legalitypauper=?, legalityvintage=?, legalitycommander=?, reserved=?, 
                                    foil=?, nonfoil=?, oversized=?, promo=?, set_id=?, game_types=?, 
                                    setcode=?, set_name=?, number=?, number_import=?, rarity=?, flavor=?, backid=?, artist=?, 
                                    price=?, price_foil=?, gatherer_uri=?, updatetime=?,
                                    f1_name=?, f1_manacost=?, f1_power=?, f1_toughness=?, f1_loyalty=?, f1_type=?, f1_ability=?, f1_colour=?, f1_artist=?, f1_image_uri=?,
                                    f2_name=?, f2_manacost=?, f2_power=?, f2_toughness=?, f2_loyalty=?, f2_type=?, f2_ability=?, f2_colour=?, f2_artist=?, f2_image_uri=?,
                                    p1_id=?, p1_component=?, p1_name=?, p1_type_line=?, p1_uri=?,
                                    p2_id=?, p2_component=?, p2_name=?, p2_type_line=?, p2_uri=?,
                                    p3_id=?, p3_component=?, p3_name=?, p3_type_line=?, p3_uri=?
                                WHERE
                                    id=?");
            if ($stmt === false):
                trigger_error('[ERROR] scryfall_bulk.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
            endif;
            $bind = $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss", 
                    $value["oracle_id"],
                    $value["tcgplayer_id"],
                    $multi_1,
                    $multi_2,
                    $value["name"],
                    $value["lang"],
                    $value["released_at"],
                    $value["uri"],
                    $value["scryfall_uri"],
                    $value["layout"],
                    $value["image_uris"]["normal"],
                    $value["mana_cost"],
                    $value["cmc"],
                    $value["type_line"],
                    $value["oracle_text"],
                    $value["power"],
                    $value["toughness"],
                    $value["loyalty"],
                    $colors,
                    $color_identity,
                    $keywords,
                    $produced_mana,
                    $value["legalities"]["standard"],
                    $value["legalities"]["pioneer"],
                    $value["legalities"]["modern"],
                    $value["legalities"]["legacy"],
                    $value["legalities"]["pauper"],
                    $value["legalities"]["vintage"],
                    $value["legalities"]["commander"],
                    $value["reserved"],
                    $value["foil"],
                    $value["nonfoil"],
                    $value["oversized"],
                    $value["promo"],
                    $value["set_id"],
                    $game_types,
                    $value["set"],
                    $value["set_name"],
                    $number_int,
                    $value["collector_number"],
                    $value["rarity"],
                    $value["flavor_text"],
                    $value["card_back_id"],
                    $value["artist"],
                    $value["prices"]["usd"],
                    $value["prices"]["usd_foil"],
                    $value["related_uris"]["gatherer"],
                    $time,
                    $name_1,
                    $manacost_1,
                    $power_1,
                    $toughness_1,
                    $loyalty_1,
                    $type_1,
                    $ability_1,
                    $colour_1,
                    $artist_1,
                    $image_1,
                    $name_2,
                    $manacost_2,
                    $power_2,
                    $toughness_2,
                    $loyalty_2,
                    $type_2,
                    $ability_2,
                    $colour_2,
                    $artist_2,
                    $image_2,
                    $id_p1,
                    $component_p1,
                    $name_p1,
                    $type_line_p1,
                    $uri_p1,
                    $id_p2,
                    $component_p2,
                    $name_p2,
                    $type_line_p2,
                    $uri_p2,
                    $id_p3,
                    $component_p3,
                    $name_p3,
                    $type_line_p3,
                    $uri_p3,
                    $id);
            if ($bind === false):
                trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                trigger_error("[ERROR] scryfall_bulk.php: Updating card details: " . $db->error, E_USER_ERROR);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Update card - no error returned",$logfile);
            endif;
            $stmt->close();        
        elseif ($action == 'price_update'): 
            $count_price_update = $count_price_update + 1;
            $stmt = $db->prepare("UPDATE `cards_scry` SET
                                    price=?, price_foil=?
                                WHERE
                                    id=uuid_to_bin(?,true)");
            if ($stmt === false):
                trigger_error('[ERROR] scryfall_bulk.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
            endif;
            $bind = $stmt->bind_param("sss", 
                    $price,
                    $foilprice,
                    $id);
            if ($bind === false):
                trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                trigger_error("[ERROR] scryfall_bulk.php: Updating card details: " . $db->error, E_USER_ERROR);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Update price - no error returned",$logfile);
            endif;
            $stmt->close();             
        elseif($action = 'nothing'):
            $count_nothing = $count_nothing + 1;
        endif;
    endif;
endforeach;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": Bulk update completed: Total $total_count, skipped $count_skip",$logfile);
$from = "From: $serveremail\r\nReturn-path: $serveremail"; 
$subject = "Obelix bulk update completed"; 
$message = "Total: $total_count; total skipped: $count_skip; total added: $count_add; total deleted: $count_delete; total refreshed: $count_update; total price updated: $count_price_update; total no action: $count_nothing";
mail($adminemail, $subject, $message, $from); 