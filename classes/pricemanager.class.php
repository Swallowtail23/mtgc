<?php
/* Version:     1.0
    Date:       11/12/23
    Name:       pricemanager.class.php
    Purpose:    Price management class
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class PriceManager
{
    private $db;
    private $logfile;
    private $useremail;
    private $message;

    public function __construct($db, $logfile, $useremail)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->useremail = $useremail;
        $this->message = new Message();
    }

    public function scryfall($cardid,$action = '')
    // Fetch TCG buy URI and price from scryfall.com JSON data
    {
        //Set up the function
        global $max_card_data_age; //From ini.php
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail for $cardid",$this->logfile);
        if(!isset($cardid)):
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail without required card id",$this->logfile);
            exit;
        endif;
        $baseurl = "https://api.scryfall.com/";
        $cardid = $this->db->real_escape_string($cardid);
        $time = time();
        //Set the URL
        $url = $baseurl."cards/".$cardid."?".$time;
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail URL for $cardid is $url",$this->logfile);

        if($row = $this->db->execute_query("Select id FROM cards_scry WHERE id = ?",[$cardid])):
            if ($row->num_rows === 0):
                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, no card with this id - exiting (2)",$this->logfile);
                exit;
            elseif ($row->num_rows === 1):
                $scrymethod = 'id';
            endif;
        else:
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API error",$this->logfile);
            $this->status = 0;
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;

        // Check for existing data, not too old, and set required action
        $rowqry = $this->db->execute_query("SELECT jsonupdatetime, tcg_buy_uri FROM scryfalljson WHERE id = ? LIMIT 1",[$cardid]);
        if ($rowqry !== false AND $rowqry->num_rows < 1):
            //No data, fetch and insert:
            $scryaction = 'get';
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with result: No data exists for $cardid, running '$scryaction'",$this->logfile);
        elseif ($rowqry !== false):
            $row = $rowqry->fetch_assoc();
            $lastjsontime = $row['jsonupdatetime'];
            $record_age = (time() - $lastjsontime);
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with result: Data exists for $cardid, $record_age seconds old",$this->logfile);
            if ($record_age > $max_card_data_age):
                //Old data, fetch and update:
                $scryaction = 'update';
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with result: Data stale (older than $max_card_data_age seconds) for $cardid, running '$scryaction'",$this->logfile);
            elseif ($action == "update"):
                //Update forced
                $scryaction = 'update';
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with result: Data update requested for $cardid, running '$scryaction'",$this->logfile);
            else:
                //data is there and is current:
                $scryaction = 'read';
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with result: Data not stale (younger than $max_card_data_age seconds) for $cardid, running '$scryaction'",$this->logfile);
            endif;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;

        // Actions:

        // UPDATE
        if($scryaction === 'update'):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with 'update' result: fetching $url",$this->logfile);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $curlresult = curl_exec($ch);
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with update: $curlresult",$this->logfile);
            curl_close($ch);
            $scryfall_result = json_decode($curlresult,true);
            if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
            else:
                $tcg_buy_uri = null;
            endif;
            if(isset($scryfall_result["prices"])):
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price section included",$this->logfile);
                if(isset($scryfall_result["prices"]["usd"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd set: {$scryfall_result["prices"]["usd"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd"] == ''):
                        $price = 0.00;
                    elseif($scryfall_result["prices"]["usd"] == 'null'):
                        $price = NULL;
                    else:
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd not set, setting to null",$this->logfile);
                    $price = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_foil"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd_foil"] == ''):
                        $price_foil = 0.00;
                    elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                        $price_foil = NULL;
                    else:
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_foil not set, setting to null",$this->logfile);
                    $price_foil = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_etched"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd_etched"] == ''):
                        $price_etched = 0.00;
                    elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                        $price_etched = NULL;
                    else:
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_etched not set, setting to null",$this->logfile);
                    $price_etched = NULL;
                endif;

                if(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = 0.00;
                elseif(($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = $price;
                elseif(($price == 0.00 OR $price === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = $price_foil;
                elseif(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL)):
                    $price_sort = $price_etched;
                elseif($price == 0.00 OR $price === NULL):
                    $price_sort = min($price_etched,$price_foil);
                elseif($price_foil == 0.00 OR $price_foil === NULL):
                    $price_sort = min($price_etched,$price);
                elseif($price_etched == 0.00 OR $price_etched === NULL):
                    $price_sort = min($price,$price_foil);
                else:
                    $price_sort = min($price,$price_foil,$price_etched);
                endif;

                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Scryfall data: price: $price, price foil: $price_foil, price etched: $price_etched, therefore $price_sort is used for sorting price",$this->logfile);
                $update_tcg_uri = 'UPDATE scryfalljson SET tcg_buy_uri = ?,jsonupdatetime = ? WHERE id = ?';
                $stmt = $this->db->prepare($update_tcg_uri);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $update_tcg_uri",$this->logfile);
                $stmt->bind_param('sss', $tcg_buy_uri,$time,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updating tcg uri failed ".$this->db->error, E_USER_ERROR,$this->logfile);
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updating tcg uri, new data written for $cardid: Insert ID: ".$stmt->insert_id,$this->logfile);
                endif;

                $update_prices = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_etched = ?,price_sort = ? WHERE id = ?';
                $stmt = $this->db->prepare($update_prices);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $update_prices",$this->logfile);
                $stmt->bind_param('sssss', $price,$price_foil,$price_etched,$price_sort,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price data update failed: ".$this->db->error, E_USER_ERROR,$this->logfile);
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price data updated for $cardid: Insert ID: ".$stmt->insert_id,$this->logfile);
                endif;
            else:
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, result does not contain a prices section",$this->logfile);
                $prices = 0;
                $price = 0;
                $price_foil = 0;
                $price_etched = 0;
            endif;
            $returnarray = array("action" => "update", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);

        // READ
        elseif($scryaction === 'read'):
            $tcg_buy_uri = $row['tcg_buy_uri'];
            
            $price = NULL;
            $price_foil = NULL;
            $price_etched = NULL;
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, returning $tcg_buy_uri",$this->logfile);
            $returnarray = array("action" => "read", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);

        // GET
        elseif($scryaction === 'get'):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with 'get' result: fetching $url",$this->logfile);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $curlresult = curl_exec($ch);
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail with get: $curlresult",$this->logfile);
            curl_close($ch);
            $scryfall_result = json_decode($curlresult,true);
            if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, result contain tcg link {$scryfall_result["purchase_uris"]["tcgplayer"]}",$this->logfile);
            else:
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, result does not contain a tcg link",$this->logfile);
                $tcg_buy_uri = 0;
            endif;
            if(isset($scryfall_result["prices"])):
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price section included",$this->logfile);
                if(isset($scryfall_result["prices"]["usd"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd set: {$scryfall_result["prices"]["usd"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd"] == ''):
                        $price = 0.00;
                    elseif($scryfall_result["prices"]["usd"] == 'null'):
                        $price = NULL;
                    else:
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd not set, setting to null",$this->logfile);
                    $price = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_foil"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd_foil"] == ''):
                        $price_foil = 0.00;
                    elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                        $price_foil = NULL;
                    else:
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_foil not set, setting to null",$this->logfile);
                    $price_foil = NULL;            
                endif;
                if(isset($scryfall_result["prices"]["usd_etched"])):
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}",$this->logfile);
                    if($scryfall_result["prices"]["usd_etched"] == ''):
                        $price_etched = 0.00;
                    elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                        $price_etched = NULL;
                    else:
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price/usd_etched not set, setting to null",$this->logfile);
                    $price_etched = NULL;            
                endif;

                if(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = 0.00;
                elseif(($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = $price;
                elseif(($price == 0.00 OR $price === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                    $price_sort = $price_foil;
                elseif(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL)):
                    $price_sort = $price_etched;
                elseif($price == 0.00 OR $price === NULL):
                    $price_sort = min($price_etched,$price_foil);
                elseif($price_foil == 0.00 OR $price_foil === NULL):
                    $price_sort = min($price_etched,$price);
                elseif($price_etched == 0.00 OR $price_etched === NULL):
                    $price_sort = min($price,$price_foil);
                else:
                    $price_sort = min($price,$price_foil,$price_etched);
                endif;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, prices are: $price, $price_foil and $price_etched; Sort price = $price_sort",$this->logfile);
            else:
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, result does not contain a prices section",$this->logfile);
                $prices = 0;
                $price = 0;
                $price_foil = 0;
                $price_etched = 0;

            endif;
            $query = 'INSERT INTO scryfalljson (id, jsonupdatetime, tcg_buy_uri) VALUES (?,?,?)';
            $stmt = $this->db->prepare($query);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $this->db->error, E_USER_ERROR);
            endif;
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $query",$this->logfile);
            $stmt->bind_param('sss', $cardid, $time, $tcg_buy_uri);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Adding update notice: failed ".$this->db->error, E_USER_ERROR,$this->logfile);
            else:
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, new data written for $cardid: Insert ID: ".$stmt->insert_id,$this->logfile);
            endif;
            if(!isset($prices)):
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, writing prices $price, $price_foil, $price_sort",$this->logfile);
                $query = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_sort = ? WHERE id = ?';
                $stmt = $this->db->prepare($query);
                $stmt->bind_param('ssss',$price,$price_foil,$price_sort,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price data update failed",$this->logfile);
                else:
                    $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $this->useremail, price data updated: Insert ID: ".$stmt->insert_id,$this->logfile);
                endif;
            endif;
            $returnarray = array("action" => "get", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);
        endif;
        return $returnarray;
    }


    public function updateCollectionValues($collection)
    {
        if($findcards = $this->db->query("SELECT
                                `$collection`.id AS id,
                                IFNULL(`$collection`.normal,0) AS mynormal,
                                IFNULL(`$collection`.foil, 0) AS myfoil,
                                IFNULL(`$collection`.etched, 0) AS myetch,
                                topvalue,
                                IFNULL(price, 0) AS normalprice,
                                IFNULL(price_foil, 0) AS foilprice,
                                IFNULL(price_etched, 0) AS etchedprice
                                FROM `$collection` LEFT JOIN `cards_scry` 
                                ON `$collection`.id = `cards_scry`.id
                                WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0")):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": SQL query succeeded",$this->logfile);
            $i = 0;
            while($row = $findcards->fetch_array(MYSQLI_ASSOC)):
                $normalqty = $row['mynormal'];
                $normalprice = $row['normalprice'];
                $foilqty = $row['myfoil'];
                $foilprice = $row['foilprice'];
                $etchedqty = $row['myetch'];
                $etchedprice = $row['etchedprice'];
                if($normalqty * $normalprice > 0):
                    $normalrate = $normalprice;
                else:
                    $normalrate = 0;
                endif;
                if($foilqty * $foilprice > 0):
                    $foilrate = $foilprice;
                else:
                    $foilrate = 0;
                endif;
                if($etchedqty * $etchedprice > 0):
                    $etchedrate = $etchedprice;
                else:
                    $etchedrate = 0;
                endif;
                $selectedrate = max($normalrate,$foilrate,$etchedrate);
                $cardid = $this->db->real_escape_string($row['id']);
                $updatemaxqry = "INSERT INTO `$collection` (topvalue,id)
                    VALUES (?,?)
                    ON DUPLICATE KEY UPDATE topvalue = ?";
                $params = [$selectedrate,$cardid,$selectedrate];
                if($updatemax = $this->db->execute_query($updatemaxqry,$params)):
                    //succeeded
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $i = $i + 1;
            endwhile;
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Collection value update completed",$this->logfile);
        else: 
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $this->db->error, E_USER_ERROR);
        endif;
        return $i;
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}