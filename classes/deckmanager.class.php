<?php
/* Version:     1.0
    Date:       25/11/23
    Name:       deckManager.class.php
    Purpose:    Initially, class for quickAdd
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

class DeckManager {
    private $db;
    private $logfile;
    
    public function __construct($db, $logfile) {
        $this->db = $db;
        $this->logfile = $logfile;
    }

    public function quickadd($decknumber,$get_string) {
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Quick add interpreter called for deck $decknumber with '$get_string'",$this->logfile);
        $quickaddstring = htmlspecialchars($get_string,ENT_NOQUOTES);
        preg_match("~^(\d*)\s*(?:([^()]+)\s*)?(?:\(([^)\s]+)(?:\s+([^)]+))?\))?~", $quickaddstring, $matches);
        if (isset($matches[1]) AND $matches[1] !== ''):
            $quickaddqty = $matches[1];
        else:
            $quickaddqty = 1;
        endif;

        if (isset($matches[2])):
            $quickaddcard = trim($matches[2]);
        else:
            $quickaddcard = '';
        endif;

        if (isset($matches[3])):
            $quickaddset = strtoupper($matches[3]);
        else:
            $quickaddset = '';
        endif;

        if (isset($matches[4])):
            $quickaddNumber = $matches[4];
        else:
            $quickaddNumber = '';
        endif;

        //Card
        $quickaddcard = htmlspecialchars_decode($quickaddcard,ENT_QUOTES);
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add called with string '$quickaddstring', interpreted as: Qty: [$quickaddqty] x Card: [$quickaddcard] Set: [$quickaddset] Collector number: [$quickaddNumber]",$this->logfile);
        if($quickaddcard !== ''):
            $quickaddcard = $this->db->escape($quickaddcard);
        endif;

        $stmt = null;
        if ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
            // Card name, setcode, and collector number provided
            $query = "SELECT id FROM cards_scry WHERE name = ? AND setcode = ? AND number_import = ? AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("sss", $quickaddcard, $quickaddset, $quickaddNumber);
        elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber === ''):
            // Card name and setcode provided
            $query = "SELECT id FROM cards_scry WHERE name = ? AND setcode = ? AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC, number ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ss", $quickaddcard, $quickaddset);
        elseif ($quickaddcard !== '' AND $quickaddset === ''):
            // Card name only provided, or with a number (but useless without setcode) - just grab a name match
            $query = "SELECT id FROM cards_scry WHERE name = ? AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC, number ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $quickaddcard);
        elseif ($quickaddcard === '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
            // Card name not provided, setcode, and collector number provided
            $query = "SELECT id FROM cards_scry WHERE setcode = ? AND number_import = ? AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ss", $quickaddset, $quickaddNumber);
        else:
            // Not enough info, cannot add
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - Not enough info to identify a card to add",$this->logfile);
            $cardtoadd = 'cardnotfound';
            return $cardtoadd;
        endif;

        if ($stmt !== null AND $stmt->execute()):
            $result = $stmt->get_result();
            if ($result->num_rows > 0):
                $row = $result->fetch_assoc();
                $stmt->close();
                $cardtoadd = $row['id'];
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add result: $cardtoadd",$this->logfile);
                adddeckcard($decknumber,$cardtoadd,"main","$quickaddqty");
                return $cardtoadd;
            else:
                $stmt->close();
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - Card not found",$this->logfile);
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;
        else:
            $stmt->close();
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - SQL error: " . $stmt->error, $this->logfile);
            $cardtoadd = 'cardnotfound';
            return $cardtoadd;
        endif;
    }

}