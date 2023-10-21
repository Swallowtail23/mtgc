<?php
/* Version:     2.0
    Date:       28/01/22
    Name:       colour.php
    Purpose:    PHP script with function to return colour name
    Notes:      
 * 
    1.0
                Initial version
 *  2.0
 *              Moved to Message class from writelog
 *  3.0
 *              Fixes for cards_scry database
 * 
 *  TO DO
 *              Should move to be database lookup rather than PHP?
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function colourfunction($colourcode)
{
    global $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"function ".__FUNCTION__.": run with input: $colourcode",$logfile);
    $decode = json_decode($colourcode);
    $colourcode = '';
    if($decode !== null):
        foreach($decode as $value):
            $colourcode = $colourcode.$value;
        endforeach;
    endif;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"function ".__FUNCTION__.": Checking card, colour identity $colourcode",$logfile);
    if (strlen($colourcode) === 1):
        if ($colourcode === "B") :
            $colour = "black";
        elseif ($colourcode === "U") :
            $colour = "blue";
        elseif ($colourcode === "G") :
            $colour = "green";
        elseif ($colourcode === "R") :
            $colour = "red";
        elseif ($colourcode === "W") :
            $colour = "white";
        elseif ($colourcode === "A") :
            $colour = "artifact";
        elseif ($colourcode === "L") :
            $colour = "land";
        elseif ($colourcode === "C") :
            $colour = "colourless";
        endif;
    elseif (strlen($colourcode) === 2) :
        if ($colourcode === "GL") :
            $colour = "dryad";
        elseif (in_array($colourcode,array('AU','UA'))) :
            $colour = "blueartifact";
        elseif (in_array($colourcode,array('AR','RA'))) :
            $colour = "redartifact";
        elseif (in_array($colourcode,array('AG','GA'))) :
            $colour = "greenartifact";
        elseif (in_array($colourcode,array('AW','WA'))) :
            $colour = "whiteartifact";
        elseif (in_array($colourcode,array('AB','BA'))) :
            $colour = "blackartifact";
        elseif (in_array($colourcode,array('AL','LA'))) :
            $colour = "landartifact";
        elseif (in_array($colourcode,array("WB","BW"))) :
            $colour = "orzhov";
        elseif (in_array($colourcode,array("GW","WG"))) :
            $colour = "selesnya";
        elseif (in_array($colourcode,array("RG","GR"))) :
            $colour = "gruul";
        elseif (in_array($colourcode,array("RB","BR"))) :
            $colour = "rakdos";
        elseif (in_array($colourcode,array("GB","BG"))) :
            $colour = "golgari";
        elseif (in_array($colourcode,array("RW","WR"))) :
            $colour = "boros";
        elseif (in_array($colourcode,array("UW","WU"))) :
            $colour = "azorius";
        elseif (in_array($colourcode,array("UB","BU"))) :
            $colour = "dimir";
        elseif (in_array($colourcode,array("UR","RU"))) :
            $colour = "izzet";
        elseif (in_array($colourcode,array("UG","GU"))) :
            $colour = "simic";
        endif;
    elseif (strlen($colourcode) === 3) :
        if (in_array($colourcode,array("WUB","BUW","UWB","UBW","WBU","BWU"))) :
            $colour = "esper";
        elseif (in_array($colourcode,array("WUG","GUW","UWG","UGW","WGU","GWU"))) :
            $colour = "bant";
        elseif (in_array($colourcode,array("RUB","RBU","URB","UBR","BRU","BUR"))) :
            $colour = "grixis";
        elseif (in_array($colourcode,array("RGW","RWG","WGR","WRG","GRW","GWR"))) :
            $colour = "naya";
        elseif (in_array($colourcode,array("BGR","BRG","RGB","RBG","GBR","GRB"))) :
            $colour = "jund";
        elseif (in_array($colourcode,array("BGW","BWG","WGB","WBG","GBW","GWB"))) :
            $colour = "junk";
        elseif (in_array($colourcode,array("UGR","URG","RGU","RUG","GUR","GRU"))) :
            $colour = "rug";
        elseif (in_array($colourcode,array("RWU","RUW","WUR","WRU","URW","UWR"))) :
            $colour = "usa";
        elseif (in_array($colourcode,array("WRB","WBR","BRW","BWR","RBW","RWB"))) :
            $colour = "oros";
        elseif (in_array($colourcode,array("BGU","BUG","UGB","UBG","GBU","GUB"))) :
            $colour = "bug";
        elseif (in_array($colourcode,array("AUR","ARU","RAU","RUA","UAR","URA"))) :
            $colour = "blueredartifact";
        elseif (in_array($colourcode,array("AWU","AUW","WUA","WAU","UAW","UWA"))) :
            $colour = "bluewhiteartifact";
        endif;
    elseif (strlen($colourcode) === 4) :
            if ((in_array(substr($colourcode,0,1),array("B","R","G","U"))) 
                    AND (in_array(substr($colourcode,1,1),array("B","R","G","U"))) 
                    AND (in_array(substr($colourcode,2,1),array("B","R","G","U"))) 
                    AND (in_array(substr($colourcode,3,1),array("B","R","G","U")))) :
                $colour = "glint";
            elseif ((in_array(substr($colourcode,0,1),array("B","R","G","W"))) 
                    AND (in_array(substr($colourcode,1,1),array("B","R","G","W"))) 
                    AND (in_array(substr($colourcode,2,1),array("B","R","G","W"))) 
                    AND (in_array(substr($colourcode,3,1),array("B","R","G","W")))):
                $colour = "dune";
            elseif ((in_array(substr($colourcode,0,1),array("W","R","G","U"))) 
                    AND (in_array(substr($colourcode,1,1),array("W","R","G","U"))) 
                    AND (in_array(substr($colourcode,2,1),array("W","R","G","U"))) 
                    AND (in_array(substr($colourcode,3,1),array("W","R","G","U")))):
                $colour = "ink";
            elseif ((in_array(substr($colourcode,0,1),array("B","W","G","U"))) 
                    AND (in_array(substr($colourcode,1,1),array("B","W","G","U"))) 
                    AND (in_array(substr($colourcode,2,1),array("B","W","G","U"))) 
                    AND (in_array(substr($colourcode,3,1),array("B","W","G","U")))):
                $colour = "witch";
            elseif ((in_array(substr($colourcode,0,1),array("B","R","W","U"))) 
                    AND (in_array(substr($colourcode,1,1),array("B","R","W","U"))) 
                    AND (in_array(substr($colourcode,2,1),array("B","R","W","U"))) 
                    AND (in_array(substr($colourcode,3,1),array("B","R","W","U")))):
                $colour = "yore";
            endif;
    elseif ((strlen($colourcode) === 5) 
            AND (in_array(substr($colourcode,0,1),array("B","R","W","U","G")))
            AND (in_array(substr($colourcode,1,1),array("B","R","W","U","G")))
            AND (in_array(substr($colourcode,2,1),array("B","R","W","U","G")))
            AND (in_array(substr($colourcode,3,1),array("B","R","W","U","G")))
            AND (in_array(substr($colourcode,4,1),array("B","R","W","U","G")))):
        $colour = "five";           
    elseif ((strlen($colourcode) === 6) 
            AND ((substr($colourcode,0,1) == "A") 
            OR (substr($colourcode,1,1) == "A") 
            OR (substr($colourcode,2,1) == "A") 
            OR (substr($colourcode,3,1) == "A") 
            OR (substr($colourcode,4,1) == "A") 
            OR (substr($colourcode,5,1) == "A"))):
        $colour = "artifactfive"; 
    elseif (strlen($colourcode) === 6) :
        if ($colourcode === "B // B") :
            $colour = "black";
        elseif ($colourcode === "U // U") :
            $colour = "blue";
        elseif ($colourcode === "G // G") :
            $colour = "green";
        elseif ($colourcode === "R // R") :
            $colour = "red";
        elseif ($colourcode === "W // W") :
            $colour = "white";
        elseif (in_array($colourcode,array("B // W","W // B"))) :
            $colour = "orzhov";
        elseif (in_array($colourcode,array("G // W","W // G"))) :
            $colour = "selesnya";
        elseif (in_array($colourcode,array("R // G","G // R"))) :
            $colour = "gruul";
        elseif (in_array($colourcode,array("B // R","R // B"))) :
            $colour = "rakdos";
        elseif (in_array($colourcode,array("B // G","G // B"))) :
            $colour = "golgari";
        elseif (in_array($colourcode,array("W // R","R // W"))) :
            $colour = "boros";
        elseif (in_array($colourcode,array("W // U","U // W"))) :
            $colour = "azorius";
        elseif (in_array($colourcode,array("B // U","U // B"))) :
            $colour = "dimir";
        elseif (in_array($colourcode,array("R // U","U // R"))) :
            $colour = "izzet";
        elseif (in_array($colourcode,array("G // U","U // G"))) :
            $colour = "simic";
        endif;
    elseif (strlen($colourcode) === 8) :
        if ($colourcode === "WU // UB") :
            $colour = "esper";
        elseif (in_array($colourcode,array("GW // WU","GU // WU"))) :
            $colour = "bant";
        elseif ($colourcode === "UB // RB") :
            $colour = "grixis";
        elseif ($colourcode === "GR // GW") :
            $colour = "naya";
        elseif (in_array($colourcode,array("GB // GR","GB // GR","RB // GR"))) :
            $colour = "jund";
        elseif (in_array($colourcode,array("WB // GB","GW // WB"))) :
            $colour = "junk";
        elseif ($colourcode === "GU // UR") :
            $colour = "rug";
        elseif ($colourcode === "UR // WR") :
            $colour = "usa";
        elseif ($colourcode === "WR // WB") :
            $colour = "oros";
        elseif ($colourcode === "GB // GU") :
            $colour = "bug";
        endif;
    else:
        $colour = "other";
    endif;
    if (empty($colour)):
        $colour = "other";
    endif;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"function ".__FUNCTION__.": Returning colour: $colour",$logfile);
    return $colour;
}
