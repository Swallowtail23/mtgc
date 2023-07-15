<?php
/* Version:     5.0
    Date:       06/07/23
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
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;

// Lists
$langs_to_skip = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la','ph','zht','ko','pt'];
$layouts_to_skip = [];

// How old to overwrite
// call script with "scryfall_bulk.php new" to always re-download
if(isset($argv[1]) AND $argv[1] == "new"):
    $max_fileage = 0;
else:
    $max_fileage = 23 * 3600;
endif;
// 23 hours for file age before downloading new one
// Delete skips, or leave in the database if they are already there?

// Scryfall bulk cards URL
$url = "https://api.scryfall.com/bulk-data/default-cards";

// Bulk file store point
$file_location = $ImgLocation.'json/bulk.json';

// Set counts
$count_inc = $count_skip = $total_count = $count_add = $count_update = $count_other = 0;

$date = date('Y-m-d');
$obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: fetching today's URL from $url",$logfile);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, "MtGCollection/1.0");
$curlresult = curl_exec($ch);
curl_close($ch);
$scryfall_bulk = json_decode($curlresult,true);
if(isset($scryfall_bulk["type"]) AND $scryfall_bulk["type"] === "default_cards"):
    $bulk_uri = $scryfall_bulk["download_uri"];
endif;

$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Download URI: $bulk_uri",$logfile);
if($max_fileage == 0):
    $download = 3;
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Called with 'new' - refreshing download",$logfile);    
elseif (file_exists($file_location) AND filesize($file_location) > 0):
    $fileage = filemtime($file_location);
    $file_date = date('d-m-Y H:i',$fileage);
    $file_size = filesize($file_location);
    if (time()-$fileage > $max_fileage):
        $download = 2;
        $obj = new Message;
        $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File old ($file_date), downloading: $bulk_uri",$logfile);
    else:
        $download = 0;
        $obj = new Message;
        $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File fresh ($file_location, $file_date, $file_size), skipping download",$logfile);    
    endif;
elseif (file_exists($file_location) AND filesize($file_location) == 0):
    $download = 1;
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: 0-byte file at ($file_location), downloading: $url",$logfile);
else:
    $download = 1;
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: No file at ($file_location), downloading: $url",$logfile);
endif;
if($download > 0):
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: downloading: $url",$logfile);
    $bulkreturn = downloadbulk($bulk_uri,$file_location);
    if ($bulkreturn == true AND file_exists($file_location) AND filesize($file_location) > 0):
        $file_size = filesize($file_location);
        $obj = new Message;
        $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: Bulk function returned no error, file at ($file_location), size greater than 0 ($file_size), proceeding",$logfile);
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File download error, waiting 5 minutes to try again",$logfile);
        sleep(300);
        $bulkreturn = downloadbulk($bulk_uri,$file_location);
        if (!($bulkreturn == true AND file_exists($file_location) AND filesize($file_location) > 0)):
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall Bulk API: File download error on retry, exiting.",$logfile);
            exit;
        endif;
    endif;
endif;

$data = Items::fromFile($file_location, ['decoder' => new ExtJsonDecoder(true)]);
foreach($data AS $key => $value):
    $total_count = $total_count + 1;
    $id = $value["id"];
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall bulk API, Record $id: $total_count",$logfile);
    $multi_1 = $multi_2 = $name_1 = $name_2 = null;
    $printed_name_1 = $printed_name_2 = $manacost_1 = $manacost_2 = null;
    $flavor_name_1 = $flavor_name_2 = $power_1 = $power_2 = null;
    $toughness_1 = $toughness_2 = $loyalty_1 = $loyalty_2 = $type_1 = $type_2 = $ability_1 = $cmc_1 = $cmc_2 = null;
    $ability_2 = $colour_1 = $colour_2 = $artist_1 = $artist_2 = $flavor_1 = $flavor_2 = $image_1 = $image_2 = null;
    $id_p1 = $component_p1 = $name_p1 = $type_line_p1 = $uri_p1 = null;
    $id_p2 = $component_p2 = $name_p2 = $type_line_p2 = $uri_p2 = null;
    $id_p3 = $component_p3 = $name_p3 = $type_line_p3 = $uri_p3 = null;
    $id_p4 = $component_p4 = $name_p4 = $type_line_p4 = $uri_p4 = null;
    $id_p5 = $component_p5 = $name_p5 = $type_line_p5 = $uri_p5 = null;
    $id_p6 = $component_p6 = $name_p6 = $type_line_p6 = $uri_p6 = null;
    $id_p7 = $component_p7 = $name_p7 = $type_line_p7 = $uri_p7 = null;
    $colors = $game_types = $color_identity = $keywords = $produced_mana = null;
    $maxpower = $minpower = $maxtoughness = $mintoughness = null;
    $maxloyalty = $minloyalty = null;
    $skip = 1; //skip by default
    //  Skips need to be specified in here
    /// Is it paper?
    foreach($value AS $key2 => $value2):
        if($key2 == 'games'):
            foreach($value2 as $game_type):
                if(($game_type === 'paper') or ($game_type === 'arena')):
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
    elseif($skip === 0):
        $time = time();
        $count_inc = $count_inc + 1;
        foreach($value AS $key2 => $value2):
            if($key2 == 'card_faces'):
                $face_loop = 1;
                foreach($value2 as $key3 => $value3):
                    if(isset($value3["name"])):
                        ${'name_'.$face_loop} = $value3["name"];
                    endif;
                    if(isset($value3["printed_name"])):
                        ${'printed_name_'.$face_loop} = $value3["printed_name"];
                    endif;
                    if(isset($value3["flavor_name"])):
                        ${'flavor_name_'.$face_loop} = $value3["flavor_name"];
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
                        ${'loyalty_'.$face_loop} = $value3["loyalty"];
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
                    if(isset($value3["flavor_text"])):
                        ${'flavor_'.$face_loop} = $value3["flavor_text"];
                    endif;
                    if(isset($value3["image_uris"]["normal"])):
                        ${'image_'.$face_loop} = $value3["image_uris"]["normal"];
                    endif;
                    if(isset($value3["cmc"])):
                        ${'cmc_'.$face_loop} = $value3["cmc"];
                    endif;
                    $face_loop = $face_loop + 1;
                endforeach;
            endif;
            if($key2 == 'all_parts'):
                $all_parts_loop = 1;
                foreach($value2 as $key4 => $value4):
                    if(isset($value4["component"]) AND $value4["component"] != "combo_piece"):
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
                    endif;
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
        $powerarray = array();
        $toughnessarray = array();
        $loyaltyarray = array();
        if(isset($value['power'])):
            array_push($powerarray,(int)$value['power']);
        endif;
        if(isset($power_1)):
            array_push($powerarray,(int)$power_1);
        endif;
        if(isset($power_2)):
            array_push($powerarray,(int)$power_2);
        endif;
        if(!empty($powerarray)):
            $maxpower = max($powerarray);
            $minpower = min($powerarray);
        endif;
        if(isset($value['toughness'])):
            array_push($toughnessarray,(int)$value['toughness']);
        endif;
        if(isset($toughness_1)):
            array_push($toughnessarray,(int)$toughness_1);
        endif;
        if(isset($toughness_2)):
            array_push($toughnessarray,(int)$toughness_2);
        endif;
        if(!empty($toughnessarray)):
            $maxtoughness = max($toughnessarray);
            $mintoughness = min($toughnessarray);
        endif;
        if(isset($value['loyalty'])):
            array_push($loyaltyarray,(int)$value['loyalty']);
        endif;
        if(isset($loyalty_1)):
            array_push($loyaltyarray,(int)$loyalty_1);
        endif;
        if(isset($loyalty_2)):
            array_push($loyaltyarray,(int)$loyalty_2);
        endif;
        if(!empty($loyaltyarray)):
            $maxloyalty = max($loyaltyarray);
            $minloyalty = min($loyaltyarray);
        endif;
        if(isset($value["colors"])):
            $colors = json_encode($value["colors"]);
        endif;
        if(isset($value["games"])):
            $game_types = json_encode($value["games"]);
        endif;
        if(isset($value["finishes"])):
            $finishes = json_encode($value["finishes"]);
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
        if(isset($value["prices"]['usd'])):
            $normal_price = $value["prices"]['usd'];
        else:
            $normal_price = null;
        endif;
        if(isset($value["prices"]['usd_foil'])):
            $foil_price = $value["prices"]['usd_foil'];
        else:
            $foil_price = null;
        endif;
        if(isset($value["prices"]['usd_etched'])):
            $etched_price = $value["prices"]['usd_etched'];
        else:
            $etched_price = null;
        endif;
        if($foil_price === null AND $normal_price === null AND $etched_price === null):
            $price_sort = null;
        elseif($foil_price === null AND $etched_price === null):
            $price_sort = $normal_price;
        elseif($normal_price === null AND $etched_price === null):
            $price_sort = $foil_price;
        elseif($foil_price === null AND $normal_price === null):
            $price_sort = $etched_price;
        elseif($normal_price === null):
            $price_sort = min($etched_price,$foil_price);
        elseif($foil_price === null):
            $price_sort = min($etched_price,$normal_price);
        elseif($etched_price === null):
            $price_sort = min($normal_price,$foil_price);
        else:
            $price_sort = min($normal_price,$foil_price,$etched_price);
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
        $stmt = $db->prepare("INSERT INTO 
                                `cards_scry`
                                (id, oracle_id, tcgplayer_id, multiverse, multiverse2,
                                name, printed_name, flavor_name, lang, release_date, api_uri, scryfall_uri, 
                                layout, image_uri, manacost, 
                                cmc, type, ability, power, toughness, loyalty, color, color_identity, 
                                keywords, generatedmana, legalitystandard, legalitypioneer, 
                                legalitymodern, legalitylegacy, legalitypauper, legalityvintage, 
                                legalitycommander, legalityalchemy, legalityhistoric, reserved, foil, nonfoil, oversized, promo, 
                                set_id, game_types, finishes, setcode, set_name, number,
                                number_import, rarity, flavor, backid, artist, price, price_foil, price_etched, 
                                gatherer_uri, updatetime,
                                f1_name, f1_manacost, f1_power, f1_toughness, f1_loyalty, f1_type, f1_ability,
                                f1_colour, f1_artist, f1_flavor, f1_image_uri, f1_cmc,
                                f1_printed_name, f1_flavor_name,
                                f2_name, f2_manacost, f2_power, f2_toughness, f2_loyalty, f2_type, f2_ability,
                                f2_colour, f2_artist, f2_flavor, f2_image_uri, f2_cmc,
                                f2_printed_name, f2_flavor_name,
                                p1_id, p1_component, p1_name, p1_type_line, p1_uri,
                                p2_id, p2_component, p2_name, p2_type_line, p2_uri,
                                p3_id, p3_component, p3_name, p3_type_line, p3_uri,
                                p4_id, p4_component, p4_name, p4_type_line, p4_uri,
                                p5_id, p5_component, p5_name, p5_type_line, p5_uri,
                                p6_id, p6_component, p6_name, p6_type_line, p6_uri,
                                p7_id, p7_component, p7_name, p7_type_line, p7_uri,
                                maxpower, minpower, maxtoughness, mintoughness, maxloyalty, minloyalty, price_sort, date_added
                                )
                            VALUES 
                                (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                                id = VALUES(id), oracle_id = VALUES(oracle_id), tcgplayer_id = VALUES(tcgplayer_id), 
                                multiverse = VALUES(multiverse), multiverse2 = VALUES(multiverse2), name = VALUES(name), 
                                printed_name = VALUES(printed_name), flavor_name = VALUES(flavor_name), 
                                lang = VALUES(lang), release_date = VALUES(release_date), api_uri = VALUES(api_uri), 
                                scryfall_uri = VALUES(scryfall_uri), layout = VALUES(layout), image_uri = VALUES(image_uri), 
                                manacost = VALUES(manacost), cmc = VALUES(cmc), type = VALUES(type), ability = VALUES(ability), 
                                power = VALUES(power), toughness = VALUES(toughness), loyalty = VALUES(loyalty), 
                                color = VALUES(color), color_identity = VALUES(color_identity), keywords = VALUES(keywords), 
                                generatedmana = VALUES(generatedmana), legalitystandard = VALUES(legalitystandard), 
                                legalitypioneer = VALUES(legalitypioneer), legalitymodern = VALUES(legalitymodern), 
                                legalitylegacy = VALUES(legalitylegacy), legalitypauper = VALUES(legalitypauper), 
                                legalityvintage = VALUES(legalityvintage), legalitycommander = VALUES(legalitycommander), 
                                legalityalchemy = VALUES(legalityalchemy), legalityhistoric = VALUES(legalityhistoric), 
                                reserved = VALUES(reserved), foil = VALUES(foil), nonfoil = VALUES(nonfoil), 
                                oversized = VALUES(oversized), promo = VALUES(promo), set_id = VALUES(set_id), 
                                game_types = VALUES(game_types), finishes = VALUES(finishes), setcode = VALUES(setcode), set_name = VALUES(set_name), number = VALUES(number),
                                number_import = VALUES(number_import), rarity = VALUES(rarity), flavor = VALUES(flavor), backid = VALUES(backid), 
                                artist = VALUES(artist), price = VALUES(price), price_foil = VALUES(price_foil), price_etched = VALUES(price_etched), 
                                gatherer_uri = VALUES(gatherer_uri), updatetime = VALUES(updatetime), 
                                f1_name = VALUES(f1_name), f1_manacost = VALUES(f1_manacost), f1_power = VALUES(f1_power), f1_toughness = VALUES(f1_toughness),
                                f1_loyalty = VALUES(f1_loyalty), f1_type = VALUES(f1_type), f1_ability = VALUES(f1_ability), 
                                f1_colour = VALUES(f1_colour), f1_artist = VALUES(f1_artist), f1_flavor = VALUES(f1_flavor), 
                                f1_image_uri = VALUES(f1_image_uri), f1_cmc = VALUES(f1_cmc), f1_printed_name = VALUES(f1_printed_name), f1_flavor_name = VALUES(f1_flavor_name),
                                f2_name = VALUES(f2_name), f2_manacost = VALUES(f2_manacost), f2_power = VALUES(f2_power), f2_toughness = VALUES(f2_toughness),
                                f2_loyalty = VALUES(f2_loyalty), f2_type = VALUES(f2_type), f2_ability = VALUES(f2_ability), 
                                f2_colour = VALUES(f2_colour), f2_artist = VALUES(f2_artist), f2_flavor = VALUES(f2_flavor), 
                                f2_image_uri = VALUES(f2_image_uri), f2_cmc = VALUES(f2_cmc),  f2_printed_name = VALUES(f2_printed_name), f2_flavor_name = VALUES(f2_flavor_name),
                                p1_id = VALUES(p1_id), p1_component = VALUES(p1_component), p1_name = VALUES(p1_name), 
                                p1_type_line = VALUES(p1_type_line), p1_uri = VALUES(p1_uri),
                                p2_id = VALUES(p2_id), p2_component = VALUES(p2_component), p2_name = VALUES(p2_name), 
                                p2_type_line = VALUES(p2_type_line), p2_uri = VALUES(p2_uri),
                                p3_id = VALUES(p3_id), p3_component = VALUES(p3_component), p3_name = VALUES(p3_name), 
                                p3_type_line = VALUES(p3_type_line), p3_uri = VALUES(p3_uri),
                                p4_id = VALUES(p4_id), p4_component = VALUES(p4_component), p4_name = VALUES(p4_name), 
                                p4_type_line = VALUES(p4_type_line), p4_uri = VALUES(p4_uri),
                                p5_id = VALUES(p5_id), p5_component = VALUES(p5_component), p5_name = VALUES(p5_name), 
                                p5_type_line = VALUES(p5_type_line), p5_uri = VALUES(p5_uri),
                                p6_id = VALUES(p6_id), p6_component = VALUES(p6_component), p6_name = VALUES(p6_name), 
                                p6_type_line = VALUES(p6_type_line), p6_uri = VALUES(p6_uri),
                                p7_id = VALUES(p7_id), p7_component = VALUES(p7_component), p7_name = VALUES(p7_name), 
                                p7_type_line = VALUES(p7_type_line), p7_uri = VALUES(p7_uri),
                                maxpower = VALUES(maxpower), minpower = VALUES(minpower), maxtoughness = VALUES(maxtoughness), 
                                mintoughness = VALUES(mintoughness), maxloyalty = VALUES(maxloyalty), minloyalty = VALUES(minloyalty), price_sort = VALUES(price_sort)
                            ");
        if ($stmt === false):
            trigger_error('[ERROR] cards.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
        endif;
        $bind = $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss", 
                $id, 
                $value["oracle_id"],
                $value["tcgplayer_id"],
                $multi_1,
                $multi_2,
                $value["name"],
                $value["printed_name"],
                $value["flavor_name"],
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
                $value["legalities"]["alchemy"],
                $value["legalities"]["historic"],
                $value["reserved"],
                $value["foil"],
                $value["nonfoil"],
                $value["oversized"],
                $value["promo"],
                $value["set_id"],
                $game_types,
                $finishes,
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
                $value["prices"]["usd_etched"],
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
                $flavor_1,
                $image_1,
                $cmc_1,
                $printed_name_1,
                $flavor_name_1,
                $name_2,
                $manacost_2,
                $power_2,
                $toughness_2,
                $loyalty_2,
                $type_2,
                $ability_2,
                $colour_2,
                $artist_2,
                $flavor_2,
                $image_2,
                $cmc_2,
                $printed_name_2,
                $flavor_name_2,
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
                $id_p4,
                $component_p4,
                $name_p4,
                $type_line_p4,
                $uri_p4,
                $id_p5,
                $component_p5,
                $name_p5,
                $type_line_p5,
                $uri_p5,
                $id_p6,
                $component_p6,
                $name_p6,
                $type_line_p6,
                $uri_p6,
                $id_p7,
                $component_p7,
                $name_p7,
                $type_line_p7,
                $uri_p7,
                $maxpower,
                $minpower,
                $maxtoughness,
                $mintoughness,
                $maxloyalty,
                $minloyalty,
                $price_sort,
                $date
                );
        if ($bind === false):
            trigger_error('[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error("[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error, E_USER_ERROR);
        else:
            $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
            if($status === 1):
                $count_add = $count_add + 1;
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Added card - no error returned; return code: $status",$logfile);
                //Fetching image
                getImageNew($value["set"], $id, $ImgLocation, $value["layout"], $two_card_detail_sections);
            elseif($status === 2):
                $count_update = $count_update + 1;
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updated card - no error returned; return code: $status",$logfile);
            else:
                $count_other = $count_other + 1;
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updated card - no error returned; return code: $status",$logfile);
            endif;
        endif;
        $stmt->close();
    endif;
endforeach;
$obj = new Message;
$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Bulk update completed: Total $total_count, skipped $count_skip, included $count_inc, added: $count_add, updated: $count_update, other: $count_other",$logfile);
$from = "From: $serveremail\r\nReturn-path: $serveremail"; 
$subject = "MTG bulk update completed"; 
$message = "Total: $total_count; total skipped: $count_skip; total included: $count_inc; total added: $count_add; total updated: $count_update";
mail($adminemail, $subject, $message, $from); 