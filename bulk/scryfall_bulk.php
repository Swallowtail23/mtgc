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

$stale = 43200;
$url = "https://api.scryfall.com/bulk-data/default-cards";
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
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: $bulk_uri",$logfile);

$bulkfile = $ImgLocation.'json/bulk.json';
$bulkreturn = downloadbulk($bulk_uri,$bulkfile);

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Local file: $bulkfile",$logfile);

$data = Items::fromFile($ImgLocation.'json/bulk.json', ['decoder' => new ExtJsonDecoder(true)]);
foreach($data AS $key => $value):
    $multi1 = $multi2 = $multi3 = "";
    $skip = 1; //skip by default
    $layout = $value["layout"];
    if($layout === 'double_faced_token' OR $layout === 'token' OR $layout === 'emblem'):
        // keep going, change nothing (default is skip)
    else:
        foreach($value AS $key2 => $value2):
            if($key2 == 'games'):
                foreach($value2 as $game_type):
                    if($game_type === 'paper'):
                        $skip = 0;
                    endif;
                endforeach;
            elseif($key2 == 'multiverse_ids'):
                $loop = 1;
                foreach($value2 as $m_id):
                    ${'multi'.$loop} = $m_id;
                    $loop = $loop + 1;
                endforeach;
            endif;    
        endforeach; 
    endif;

    $id = $value["id"];
    if($row = $db->select('id,updatetime','cards_scry',"WHERE id='$id'")):
        if ($row->num_rows === 0):
            if($skip === 0):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, no existing record, adding",$logfile);
                $action = 'add';
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, no existing record, skipping",$logfile);
                $action = 'nothing';
            endif;
        elseif($row->num_rows === 1):
            $row = $row->fetch_assoc();
            $lastupdate = $row["updatetime"];
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, matched an existing record...",$logfile);
            if($skip === 1):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, on skip list, delete",$logfile);
                $action = 'delete';
            elseif(time() - $lastupdate < $stale):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, existing record fresh",$logfile);
                $action = 'nothing';
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, existing record stale",$logfile);
                $action = 'update';
            endif;
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, double-ups when id is unique ($id)",$logfile);
            $action = 'error';
        endif;
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API error",$logfile);
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, action is: $action",$logfile);
    if($action == 'delete'):
        if( $db->delete('cards_scry', "WHERE id = '$id'") === TRUE):
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API on skip list, deleting",$logfile);
        else:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API error",$logfile);
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    elseif($action == 'add'):
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
        $stmt = $db->prepare("INSERT INTO 
                                `cards_scry`
                                (id, oracle_id, tcgplayer_id, multiverse, multiverse2, multiverse3,
                                name, lang, release_date, api_uri, scryfall_uri, 
                                layout, image_uri, manacost, 
                                cmc, type, ability, power, toughness, loyalty, color, color_identity, 
                                keywords, generatedmana, legalitystandard, legalitypioneer, 
                                legalitymodern, legalitylegacy, legalitypauper, legalityvintage, 
                                legalitycommander, reserved, foil, nonfoil, oversized, promo, 
                                set_id, game_types, setcode, set_name,
                                number, rarity, flavor, backid, artist, price, price_foil, 
				gatherer_uri, updatetime)
                            VALUES 
                                (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $stmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssssss", 
                $value["id"], 
                $value["oracle_id"],
                $value["tcgplayer_id"],
                $multi1,
                $multi2,
                $multi3,
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
                $value["collector_number"],
                $value["rarity"],
                $value["flavor_text"],
                $value["card_back_id"],
                $value["artist"],
                $value["prices"]["usd"],
                $value["prices"]["usd_foil"],
                $value["related_uris"]["gatherer"],
                $time);
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        if (!$stmt->execute()):
            trigger_error("[ERROR] cards.php: Writing new card details: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Add card - no error returned",$logfile);
        endif;
        $stmt->close();
    elseif ($action == 'update'):
        $time = time();
        if(isset($value["multiverse_ids"])):
            $multiverse = json_encode($value["multiverse_ids"]);
        endif;
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
        $stmt = $db->prepare("UPDATE `cards_scry` SET
                                oracle_id=?, tcgplayer_id=?, multiverse=?, multiverse2=?, multiverse3=?, 
                                name=?, lang=?, release_date=?, api_uri=?, scryfall_uri=?, layout=?, 
                                image_uri=?, manacost=?, cmc=?, type=?, ability=?, power=?, toughness=?, 
                                loyalty=?, color=?, color_identity=?, keywords=?, generatedmana=?, 
                                legalitystandard=?, legalitypioneer=?, legalitymodern=?, legalitylegacy=?, 
                                legalitypauper=?, legalityvintage=?, legalitycommander=?, reserved=?, 
                                foil=?, nonfoil=?, oversized=?, promo=?, set_id=?, game_types=?, 
                                setcode=?, set_name=?, number=?, rarity=?, flavor=?, backid=?, artist=?, 
                                price=?, price_foil=?, gatherer_uri=?, updatetime=?
                            WHERE
                                id=?");
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $stmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssssss", 
                $value["oracle_id"],
                $value["tcgplayer_id"],
                $multi1,
                $multi2,
                $multi3,
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
                $value["collector_number"],
                $value["rarity"],
                $value["flavor_text"],
                $value["card_back_id"],
                $value["artist"],
                $value["prices"]["usd"],
                $value["prices"]["usd_foil"],
                $value["related_uris"]["gatherer"],
                $time,
                $value["id"]);
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        if (!$stmt->execute()):
            trigger_error("[ERROR] cards.php: Updating card details: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Update card - no error returned",$logfile);
        endif;
        $stmt->close();        
    endif;
endforeach;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Bulk update completed",$logfile);