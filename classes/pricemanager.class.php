<?php
/* Version:     1.3
    Date:       01/03/25
    Name:       pricemanager.class.php
    Purpose:    Price management class
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson
    
 *  1.0
                Initial version
  
    1.1         20/01/24
 *              Move to logMessage
 * 
 *  1.2         23/08/24
 *              MTGC-123 - Use normal value if needed for Top Value
 * 
 *  1.3         01/03/25
 *              MTGC-124 - Move last price calc function here from functions file
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
        $this->message = new Message($this->logfile);
    }

    public function scryfall($cardid,$action = '')
    // Fetch TCG buy URI and price from scryfall.com JSON data
    {
        //Set up the function
        global $max_card_data_age; //From ini.php
        $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail for $cardid");
        if(!isset($cardid)):
            $this->message->logMessage('[ERROR]',"Scryfall API by $this->useremail without required card id");
            exit;
        endif;
        $baseurl = "https://api.scryfall.com/";
        $cardid = $this->db->real_escape_string($cardid);
        $time = time();
        //Set the URL
        $url = $baseurl."cards/".$cardid."?".$time;
        $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail URL for $cardid is $url");

        if($row = $this->db->execute_query("Select id FROM cards_scry WHERE id = ?",[$cardid])):
            if ($row->num_rows === 0):
                $this->message->logMessage('[ERROR]',"Scryfall API by $this->useremail, no card with this id - returning 'nocard'");
                $returnarray = array("action" => "nocard");
                return $returnarray;
            elseif ($row->num_rows === 1):
                $scrymethod = 'id';
            endif;
        else:
            $this->message->logMessage('[ERROR]',"Scryfall API error");
            $this->status = 0;
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;

        // Check for existing data, not too old, and set required action
        $rowqry = $this->db->execute_query("SELECT jsonupdatetime, tcg_buy_uri FROM scryfalljson WHERE id = ? LIMIT 1",[$cardid]);
        if ($rowqry !== false AND $rowqry->num_rows < 1):
            //No data, fetch and insert:
            $scryaction = 'get';
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with result: No data exists for $cardid, running '$scryaction'");
        elseif ($rowqry !== false):
            $row = $rowqry->fetch_assoc();
            $lastjsontime = $row['jsonupdatetime'];
            $record_age = (time() - $lastjsontime);
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with result: Data exists for $cardid, $record_age seconds old");
            if ($record_age > $max_card_data_age):
                //Old data, fetch and update:
                $scryaction = 'update';
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with result: Data stale (older than $max_card_data_age seconds) for $cardid, running '$scryaction'");
            elseif ($action == "update"):
                //Update forced
                $scryaction = 'update';
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with result: Data update requested for $cardid, running '$scryaction'");
            else:
                //data is there and is current:
                $scryaction = 'read';
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with result: Data not stale (younger than $max_card_data_age seconds) for $cardid, running '$scryaction'");
            endif;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;

        // Actions:

        // UPDATE
        if($scryaction === 'update'):
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with 'update' result: fetching $url");
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
                CURLOPT_USERAGENT => "MtGCollection/1.0",
                CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
                );
            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $curlresult = curl_exec($ch);
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with update: $curlresult");
            curl_close($ch);
            $scryfall_result = json_decode($curlresult,true);
            if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
            else:
                $tcg_buy_uri = null;
            endif;
            if(isset($scryfall_result["prices"])):
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price section included");
                if(isset($scryfall_result["prices"]["usd"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd set: {$scryfall_result["prices"]["usd"]}");
                    if($scryfall_result["prices"]["usd"] == ''):
                        $price = 0.00;
                    elseif($scryfall_result["prices"]["usd"] == 'null'):
                        $price = NULL;
                    else:
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd not set, setting to null");
                    $price = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_foil"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}");
                    if($scryfall_result["prices"]["usd_foil"] == ''):
                        $price_foil = 0.00;
                    elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                        $price_foil = NULL;
                    else:
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_foil not set, setting to null");
                    $price_foil = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_etched"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}");
                    if($scryfall_result["prices"]["usd_etched"] == ''):
                        $price_etched = 0.00;
                    elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                        $price_etched = NULL;
                    else:
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_etched not set, setting to null");
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

                $this->message->logMessage('[DEBUG]',"Scryfall data: price: $price, price foil: $price_foil, price etched: $price_etched, therefore $price_sort is used for sorting price");
                $update_tcg_uri = 'UPDATE scryfalljson SET tcg_buy_uri = ?,jsonupdatetime = ? WHERE id = ?';
                $stmt = $this->db->prepare($update_tcg_uri);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $this->message->logMessage('[NOTICE]',"$update_tcg_uri");
                $stmt->bind_param('sss', $tcg_buy_uri,$time,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->logMessage('[ERROR]',"Updating tcg uri failed ".$this->db->error);
                else:
                    $this->message->logMessage('[DEBUG]',"Updating tcg uri, new data written for $cardid: Insert ID: ".$stmt->insert_id);
                endif;

                $update_prices = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_etched = ?,price_sort = ? WHERE id = ?';
                $stmt = $this->db->prepare($update_prices);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $this->message->logMessage('[NOTICE]',"$update_prices");
                $stmt->bind_param('sssss', $price,$price_foil,$price_etched,$price_sort,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->logMessage('[ERROR]',"Scryfall API by $this->useremail, price data update failed: ".$this->db->error);
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price data updated for $cardid: Insert ID: ".$stmt->insert_id);
                endif;
            else:
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, result does not contain a prices section");
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
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, returning $tcg_buy_uri");
            $returnarray = array("action" => "read", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);

        // GET
        elseif($scryaction === 'get'):
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with 'get' result: fetching $url");
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
                CURLOPT_USERAGENT => "MtGCollection/1.0",
                CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
                );
            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $curlresult = curl_exec($ch);
            $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail with get: $curlresult");
            curl_close($ch);
            $scryfall_result = json_decode($curlresult,true);
            if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, result contain tcg link {$scryfall_result["purchase_uris"]["tcgplayer"]}");
            else:
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, result does not contain a tcg link");
                $tcg_buy_uri = 0;
            endif;
            if(isset($scryfall_result["prices"])):
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price section included");
                if(isset($scryfall_result["prices"]["usd"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd set: {$scryfall_result["prices"]["usd"]}");
                    if($scryfall_result["prices"]["usd"] == ''):
                        $price = 0.00;
                    elseif($scryfall_result["prices"]["usd"] == 'null'):
                        $price = NULL;
                    else:
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd not set, setting to null");
                    $price = NULL;
                endif;
                if(isset($scryfall_result["prices"]["usd_foil"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}");
                    if($scryfall_result["prices"]["usd_foil"] == ''):
                        $price_foil = 0.00;
                    elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                        $price_foil = NULL;
                    else:
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_foil not set, setting to null");
                    $price_foil = NULL;            
                endif;
                if(isset($scryfall_result["prices"]["usd_etched"])):
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}");
                    if($scryfall_result["prices"]["usd_etched"] == ''):
                        $price_etched = 0.00;
                    elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                        $price_etched = NULL;
                    else:
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price/usd_etched not set, setting to null");
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
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, prices are: $price, $price_foil and $price_etched; Sort price = $price_sort");
            else:
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, result does not contain a prices section");
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
            $this->message->logMessage('[DEBUG]',"$query");
            $stmt->bind_param('sss', $cardid, $time, $tcg_buy_uri);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                $this->message->logMessage('[ERROR]',"Adding update notice: failed ".$this->db->error);
            else:
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, new data written for $cardid: Insert ID: ".$stmt->insert_id);
            endif;
            if(!isset($prices)):
                $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, writing prices $price, $price_foil, $price_sort");
                $query = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_sort = ? WHERE id = ?';
                $stmt = $this->db->prepare($query);
                $stmt->bind_param('ssss',$price,$price_foil,$price_sort,$cardid);
                if ($stmt === false):
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $this->db->error, E_USER_ERROR);
                endif;
                $exec = $stmt->execute();
                if ($exec === false):
                    $this->message->logMessage('[ERROR]',"Scryfall API by $this->useremail, price data update failed");
                else:
                    $this->message->logMessage('[DEBUG]',"Scryfall API by $this->useremail, price data updated: Insert ID: ".$stmt->insert_id);
                endif;
            endif;
            $returnarray = array("action" => "get", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);
        endif;
        return $returnarray;
    }


    public function updateCollectionValues($collection,$cardid = "")
    {
        $i = 0; // Counter for updated rows
        $findcards = false; // Will store our result set
        
        if($cardid === ""): //Full collection value update
            $query = "SELECT
                        `$collection`.id AS id,
                        IFNULL(`$collection`.normal,0) AS mynormal,
                        IFNULL(`$collection`.foil, 0) AS myfoil,
                        IFNULL(`$collection`.etched, 0) AS myetch,
                        topvalue,
                        IFNULL(price, 0) AS normalprice,
                        CASE 
                            WHEN price_foil IS NOT NULL THEN price_foil
                            WHEN price_foil IS NULL AND cards_scry.foil = 1 AND `$collection`.foil IS NOT NULL AND `$collection`.foil > 0 THEN IFNULL(price, 0)
                            ELSE 0
                        END AS foilprice,
                        CASE 
                            WHEN price_etched IS NOT NULL THEN price_etched
                            WHEN price_etched IS NULL AND `$collection`.etched IS NOT NULL AND `$collection`.etched > 0 THEN IFNULL(price, 0)
                            ELSE 0
                        END AS etchedprice
                        FROM `$collection` LEFT JOIN `cards_scry` 
                        ON `$collection`.id = `cards_scry`.id
                        WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0";
            $findcards = $this->db->query($query); // Simple query execution
        else:              // Single card value update
            $query = "SELECT
                        `$collection`.id AS id,
                        IFNULL(`$collection`.normal,0) AS mynormal,
                        IFNULL(`$collection`.foil, 0) AS myfoil,
                        IFNULL(`$collection`.etched, 0) AS myetch,
                        notes,
                        topvalue,
                        IFNULL(price, 0) AS normalprice,
                        CASE 
                            WHEN price_foil IS NOT NULL THEN price_foil
                            WHEN price_foil IS NULL AND cards_scry.foil = 1 AND `$collection`.foil IS NOT NULL AND `$collection`.foil > 0 THEN IFNULL(price, 0)
                            ELSE 0
                        END AS foilprice,
                        CASE 
                            WHEN price_etched IS NOT NULL THEN price_etched
                            WHEN price_etched IS NULL AND `$collection`.etched IS NOT NULL AND `$collection`.etched > 0 THEN IFNULL(price, 0)
                            ELSE 0
                        END AS etchedprice
                        FROM `$collection` LEFT JOIN `cards_scry` 
                        ON `$collection`.id = `cards_scry`.id
                        WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0
                        AND `$collection`.id = ?";
                    $stmt = $this->db->prepare($query);
            if ($stmt):
                $stmt->bind_param("s", $cardid);
                $stmt->execute();
                $findcards = $stmt->get_result();
                $stmt->close();
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $this->db->error, E_USER_ERROR);
            endif;
        endif;
        if($findcards):
            $this->message->logMessage('[DEBUG]',"SQL query succeeded");
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
                $i++;
            endwhile;
            $this->message->logMessage('[NOTICE]',"Value update completed (row count: $i)");
        else: 
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $this->db->error, E_USER_ERROR);
        endif;
        return $i;
    }

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}