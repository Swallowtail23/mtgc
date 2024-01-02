<?php
/* Version:     7.0
    Date:       02/01/24
    Name:       criteria.php
    Purpose:    PHP script to build search criteria
    Notes:      
 * 
    1.0
                Initial version
    2.0
                Cards_scry refactoring
 *  3.0
 *              Add Arena legalities
 *  4.0
 *              PHP 8.1 compatibility
 *  5.0
 *              Add [set] search interpretation
 *
 *  6.0         09/12/23
 *              Move main card search to parameterised queries
 * 
 *  7.0         02/01/24
 *              Add language search capability
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

if (empty($_GET)):
    $validsearch = "";
else:
    $params = [];
    if ($adv != "yes") :
        // Not an advanced search called
        if (strlen($name) > 2): // Needs to have more than 2 characters to search
            if ($exact === "yes"):  // Used in 'Primary Printings' search from card_detail page
                $criteria = "(cards_scry.name LIKE ? OR cards_scry.f1_name LIKE ? OR cards_scry.f2_name LIKE ? 
                            OR cards_scry.printed_name LIKE ? OR cards_scry.f1_printed_name LIKE ? OR cards_scry.f2_printed_name LIKE ?
                            OR cards_scry.flavor_name LIKE ? OR cards_scry.f1_flavor_name LIKE ? OR cards_scry.f2_flavor_name LIKE ?) AND primary_card = 1 ";
                $params = array_fill(0, 9, $name);
            elseif ($allprintings === "yes"):  // Used in 'All Printings' search from card_detail page
                $criteria = "(cards_scry.name LIKE ? OR cards_scry.f1_name LIKE ? OR cards_scry.f2_name LIKE ? 
                            OR cards_scry.printed_name LIKE ? OR cards_scry.f1_printed_name LIKE ? OR cards_scry.f2_printed_name LIKE ?
                            OR cards_scry.flavor_name LIKE ? OR cards_scry.f1_flavor_name LIKE ? OR cards_scry.f2_flavor_name LIKE ?) ";
                $params = array_fill(0, 9, $name);
            else:
                $criteria = "(cards_scry.name LIKE ? OR cards_scry.f1_name LIKE ? OR cards_scry.f2_name LIKE ?
                            OR cards_scry.printed_name LIKE ? OR cards_scry.f1_printed_name LIKE ? OR cards_scry.f2_printed_name LIKE ?
                            OR cards_scry.flavor_name LIKE ? OR cards_scry.f1_flavor_name LIKE ? OR cards_scry.f2_flavor_name LIKE ?) ";
                $params = array_fill(0, 9, "%{$name}%");
                if (!empty($searchLang)):
                    $criteria .= "AND lang LIKE ? ";
                    $params[] = $searchLang;
                else:
                    $criteria .= "AND primary_card = 1 ";
                endif;
            endif;
            if (!empty($setcodesearch)):
                $criteria .= "AND setcode LIKE ? ";
                $params[] = $setcodesearch;
            endif;

            $order = "ORDER BY cards_scry.name ASC, set_date DESC, number ASC, cs_id ASC ";
            $query = $selectAll.$criteria.$order.$sorting;
            $validsearch = "true";
        else: 
            // Not enough characters - set as a not valid search
            $qtyresults = 0;
            $validsearch = "false";
        endif;
    elseif ($adv == "yes" ):
        // An advanced search called
        $criteriaNTA = "";
        if ($searchnotes === "yes"):
            $criteriaNTA = "$mytable.notes LIKE ? ";
            $params[] = "%$name%";
        elseif (empty($name) AND (empty($searchname) AND empty($searchtype) AND empty($searchsetcode) AND empty($searchpromo) AND empty($searchability) AND empty($searchabilityexact))):
            $criteriaNTA .= "cards_scry.name LIKE '%%' ";
        elseif (empty($searchname) AND empty($searchtype) AND empty($searchsetcode) AND empty($searchpromo) AND empty($searchability) AND empty($searchabilityexact)):
            $criteriaNTA .= "cards_scry.name LIKE ? ";
            $params[] = "%$name%";
        elseif (!empty($searchpromo) AND empty($name)):
            $criteriaNTA .= "cards_scry.promo_types IS NOT NULL ";
        elseif (!empty($searchpromo) AND !empty($name)):
            $criteriaNTA .= "cards_scry.promo_types LIKE ? ";
            $params[] = "%$name%";
        else:
            if ($searchname === "yes"):
                if ($exact === "yes"):
                    $criteriaNTA = "(cards_scry.name LIKE ? OR cards_scry.f1_name LIKE ? OR cards_scry.f2_name LIKE ? 
                            OR cards_scry.printed_name LIKE ? OR cards_scry.f1_printed_name LIKE ? OR cards_scry.f2_printed_name LIKE ?
                            OR cards_scry.flavor_name LIKE ? OR cards_scry.f1_flavor_name LIKE ? OR cards_scry.f2_flavor_name LIKE ?) ";
                    $params = array_fill(0, 9, $name);
                else:
                    $criteriaNTA = "(cards_scry.name LIKE ? OR cards_scry.f1_name LIKE ? OR cards_scry.f2_name LIKE ?
                            OR cards_scry.printed_name LIKE ? OR cards_scry.f1_printed_name LIKE ? OR cards_scry.f2_printed_name LIKE ?
                            OR cards_scry.flavor_name LIKE ? OR cards_scry.f1_flavor_name LIKE ? OR cards_scry.f2_flavor_name LIKE ?) ";
                    $params = array_fill(0, 9, "%{$name}%");
                endif;
            endif;
            if ($searchtype === "yes"):
                if (!empty($criteriaNTA)) :
                    $criteriaNTA .= "OR ";
                endif;
                $criteriaNTA .= "cards_scry.type LIKE ? ";
                $params[] = "%$name%";
            endif;
            if ($searchsetcode === "yes"):
                if (!empty($criteriaNTA)) :
                    $criteriaNTA .= "OR ";
                endif;
                $criteriaNTA .= "cards_scry.setcode LIKE ? ";
                $params[] = $name;
            endif;
            if ($searchability === "yes"):
                $abilitytext = "";
                $parts = explode(" ",trim($name));
                foreach ($parts as $part):
                    $part = "+".$part;
                    $abilitytext .= $part." ";
                endforeach;
                if (!empty($criteriaNTA)) :
                    $criteriaNTA .= "OR ";
                endif;
                $criteriaNTA .= "MATCH (cards_scry.ability,cards_scry.f1_ability,cards_scry.f2_ability) AGAINST (? IN BOOLEAN MODE) ";
                $params[] = $abilitytext;
            elseif ($searchabilityexact === "yes"):
                if (!empty($criteriaNTA)) :
                    $criteriaNTA .= "OR ";
                endif;
                $criteriaNTA .= "(cards_scry.ability LIKE ? OR cards_scry.f1_ability LIKE ? OR cards_scry.f1_ability LIKE ?) ";
                $params = array_fill(0, 3, "%{$name}%");
            endif;
        endif;
        $criteria = "(".$criteriaNTA.") ";
        // Colours first
        $criteriaCol = "";
        if ($white === "yes"):
            $criteriaCol = "(cards_scry.color LIKE '%W%' OR cards_scry.color_identity LIKE '%W%' OR cards_scry.f1_colour LIKE '%W%' OR cards_scry.f2_colour LIKE '%W%' )";
        endif;
        if ($blue === "yes"):
            if (!empty($criteriaCol)) :
                $criteriaCol .= $colourOp." ";
            endif;
            $criteriaCol .= "(cards_scry.color LIKE '%U%' OR cards_scry.color_identity LIKE '%U%' OR cards_scry.f1_colour LIKE '%U%' OR cards_scry.f2_colour LIKE '%U%' )";
        endif;
        if ($black === "yes"):
            if (!empty($criteriaCol)) :
                $criteriaCol .= $colourOp." ";
            endif;
            $criteriaCol .= "(cards_scry.color LIKE '%B%' OR cards_scry.color_identity LIKE '%B%' OR cards_scry.f1_colour LIKE '%B%' OR cards_scry.f2_colour LIKE '%B%' )";
        endif;
        if ($red === "yes"):
            if (!empty($criteriaCol)) :
                $criteriaCol .= $colourOp." ";
            endif;
            $criteriaCol .= "(cards_scry.color LIKE '%R%' OR cards_scry.color_identity LIKE '%R%' OR cards_scry.f1_colour LIKE '%R%' OR cards_scry.f2_colour LIKE '%R%' )";
        endif;
        if ($green === "yes"):
            if (!empty($criteriaCol)) :
                $criteriaCol .= $colourOp." ";
            endif;
            $criteriaCol .= "(cards_scry.color LIKE '%G%' OR cards_scry.color_identity LIKE '%G%' OR cards_scry.f1_colour LIKE '%G%' OR cards_scry.f2_colour LIKE '%G%' )";
        endif;
        if ($colourless === "yes"):
            if (!empty($criteriaCol)) :
                $criteriaCol .= $colourOp." ";
            endif;
            $criteriaCol .= "(cards_scry.color LIKE '%[]%' OR cards_scry.f1_colour LIKE '%[]%' OR cards_scry.f2_colour LIKE '%[]%' )";
        endif;
        if (!empty($criteriaCol)) :
            $criteria .= "AND (".$criteriaCol.") ";
        endif;
        // Colour exclusivity?
        if ($colourExcl == "ONLY"):
            if (empty($white)):
                $criteria .= "AND ((cards_scry.color IS NULL OR cards_scry.color NOT LIKE '%W%') and (cards_scry.color_identity IS NULL OR cards_scry.color_identity NOT LIKE '%W%') and (cards_scry.f1_colour IS NULL OR cards_scry.f1_colour NOT LIKE '%W%') and (cards_scry.f2_colour IS NULL OR cards_scry.f2_colour NOT LIKE '%W%')) ";
            endif;
            if (empty($blue)):
                $criteria .= "AND ((cards_scry.color IS NULL OR cards_scry.color NOT LIKE '%U%') and (cards_scry.color_identity IS NULL OR cards_scry.color_identity NOT LIKE '%U%')and (cards_scry.f1_colour IS NULL OR cards_scry.f1_colour NOT LIKE '%U%') and (cards_scry.f2_colour IS NULL OR cards_scry.f2_colour NOT LIKE '%U%')) ";
            endif;
            if (empty($red)):
                $criteria .= "AND ((cards_scry.color IS NULL OR cards_scry.color NOT LIKE '%R%') and (cards_scry.color_identity IS NULL OR cards_scry.color_identity NOT LIKE '%R%')and (cards_scry.f1_colour IS NULL OR cards_scry.f1_colour NOT LIKE '%R%') and (cards_scry.f2_colour IS NULL OR cards_scry.f2_colour NOT LIKE '%R%')) ";
            endif;
            if (empty($green)):
                $criteria .= "AND ((cards_scry.color IS NULL OR cards_scry.color NOT LIKE '%G%') and (cards_scry.color_identity IS NULL OR cards_scry.color_identity NOT LIKE '%G%')and (cards_scry.f1_colour IS NULL OR cards_scry.f1_colour NOT LIKE '%G%') and (cards_scry.f2_colour IS NULL OR cards_scry.f2_colour NOT LIKE '%G%')) ";
            endif;
            if (empty($black)):
                $criteria .= "AND ((cards_scry.color IS NULL OR cards_scry.color NOT LIKE '%B%') and (cards_scry.color_identity IS NULL OR cards_scry.color_identity NOT LIKE '%B%')and (cards_scry.f1_colour IS NULL OR cards_scry.f1_colour NOT LIKE '%B%') and (cards_scry.f2_colour IS NULL OR cards_scry.f2_colour NOT LIKE '%B%')) ";
            endif;
        endif;
        // New
        $criteriaNew = "";
        if ($new === "yes"):
            $criteriaNew = "DATEDIFF(CURDATE(),date_added) < 7 ";
        endif;
        if (!empty($criteriaNew)) :
            $criteria .= "AND (".$criteriaNew.") ";
        endif;
        // Then rarity
        $criteriaRty = "";
        if ($common === "yes"):
            $criteriaRty = "cards_scry.rarity LIKE 'common' ";
        endif;
        if ($uncommon === "yes"):
            if (!empty($criteriaRty)) :
                $criteriaRty .= "OR ";
            endif;
            $criteriaRty .= "cards_scry.rarity LIKE 'uncommon' ";
        endif;
        if ($rare === "yes"):
            if (!empty($criteriaRty)) :
                $criteriaRty .= "OR ";
            endif;
            $criteriaRty .= "cards_scry.rarity LIKE 'rare' ";
        endif;
        if ($mythic === "yes"):
            if (!empty($criteriaRty)) :
                $criteriaRty .= "OR ";
            endif;
            $criteriaRty .= "cards_scry.rarity LIKE 'mythic' ";
        endif;
        if (!empty($criteriaRty)) :
            $criteria .= "AND (".$criteriaRty.") ";
        endif;

        // Then type
        $criteriaType = "";
        if ($creature === "yes"):
            $criteriaType = "cards_scry.type LIKE '%creature%' ";
        endif;
        if ($instant === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%instant%' ";
        endif;
        if ($sorcery === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%sorcery%' ";
        endif;
        if ($enchantment === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%enchantment%' ";
        endif;
        if ($planeswalker === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%planeswalker%' ";
        endif;
        if ($tribal === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "(cards_scry.type LIKE '%tribal%' OR cards_scry.type LIKE '%kindred%') ";
        endif;    
        if ($legendary === "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%legendary%' ";
        endif;
        if ($artifact == "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%artifact%' ";
        endif;
        if ($land == "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%land%' ";
        endif;
        if ($battle == "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "cards_scry.type LIKE '%battle%' ";
        endif;
        if ($token == "yes"):
            if (!empty($criteriaType)) :
                $criteriaType .= "OR ";
            endif;
            $criteriaType .= "(cards_scry.layout LIKE '%token%' OR cards_scry.layout LIKE '%emblem%') ";
        endif;
        if (!empty($criteriaType)) :
            $criteria .= "AND (".$criteriaType.") ";
        endif;  

        // Then game type
        $criteriaGameType = "";
        if ($paper === "yes"):
            $criteriaGameType = "cards_scry.game_types LIKE '%paper%'";
        endif;
        if ($arena === "yes"):
            if (!empty($criteriaGameType)) :
                $criteriaGameType .= $gametypeOp." ";
            endif;
            $criteriaGameType .= "cards_scry.game_types LIKE '%arena%'";
        endif;
        if ($online === "yes"):
            if (!empty($criteriaGameType)) :
                $criteriaGameType .= $gametypeOp." ";
            endif;
            $criteriaGameType .= "cards_scry.game_types LIKE '%mtgo%'";
        endif;
        if (!empty($criteriaGameType)) :
            $criteria .= "AND (".$criteriaGameType.") ";
        endif;  
        // Game type exclusivity?
        if ($gametypeExcl == "ONLY"):
            if (empty($paper)):
                $criteria .= "AND cards_scry.game_types NOT LIKE '%paper%' ";
            endif;
            if (empty($arena)):
                $criteria .= "AND cards_scry.game_types NOT LIKE '%arena%' ";
            endif;
            if (empty($online)):
                $criteria .= "AND cards_scry.game_types NOT LIKE '%mtgo%' ";
            endif;
        endif;

        // Tribal 
        $criteriaTribe = "";
        if ($tribe === "merfolk"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%merfolk%' ";
        endif;
        if ($tribe === "goblin"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%goblin%' ";
        endif;
        if ($tribe === "treefolk"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%treefolk%' ";
        endif;
        if ($tribe === "elf"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%elf%' ";
        endif;    
        if ($tribe === "sliver"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%sliver%' ";
        endif;    
        if ($tribe === "human"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%human%' ";
        endif;    
        if ($tribe === "spider"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%spider%' ";
        endif;  
        if ($tribe === "zombie"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%zombie%' ";
        endif;    
        if ($tribe === "vampire"):
            if (!empty($criteriaTribe)) :
                $criteriaTribe .= "OR ";
            endif;
            $criteriaTribe .= "cards_scry.type LIKE '%vampire%' ";
        endif;    
        if (!empty($criteriaTribe)) :
            $criteria .= "AND (".$criteriaTribe.") ";
        endif;    

        // Sets
        $criteriaSets = "";
        if (!empty($selectedSets)):
            foreach($selectedSets AS $key=>$values):
                if (!empty($criteriaSets)) :
                    $criteriaSets .= "OR ";
                endif;
                $criteriaSets .= "cards_scry.setcode LIKE '$values' ";
            endforeach;
            if (!empty($criteriaSets)) :
                $criteria .= "AND (".$criteriaSets.") ";
            endif;    
        endif;

        //CMC / Power / toughness / loyalty
        if (!empty($cmcvalue)):
            if ($cmcoperator === "ltn"):
                $criteria .= "AND (
                       (cards_scry.cmc IS NOT NULL AND cards_scry.cmc < ".$cmcvalue.")
                         OR 
                       (cards_scry.f1_cmc IS NOT NULL AND cards_scry.f1_cmc < ".$cmcvalue.")
                         OR 
                       (cards_scry.f2_cmc IS NOT NULL AND cards_scry.f2_cmc < ".$cmcvalue.")
                         ) ";
            elseif ($cmcoperator === "gtr"):
                $criteria .= "AND (
                       (cards_scry.cmc IS NOT NULL AND cards_scry.cmc > ".$cmcvalue.")
                         OR 
                       (cards_scry.f1_cmc IS NOT NULL AND cards_scry.f1_cmc > ".$cmcvalue.")
                         OR 
                       (cards_scry.f2_cmc IS NOT NULL AND cards_scry.f2_cmc > ".$cmcvalue.")
                         ) ";
            elseif ($cmcoperator === "eq"):
                $criteria .= "AND (
                       (cards_scry.cmc IS NOT NULL AND cards_scry.cmc = ".$cmcvalue.")
                         OR 
                       (cards_scry.f1_cmc IS NOT NULL AND cards_scry.f1_cmc = ".$cmcvalue.")
                         OR 
                       (cards_scry.f2_cmc IS NOT NULL AND cards_scry.f2_cmc = ".$cmcvalue.")
                         ) ";
            endif;
        endif;
        if (!empty($power)):
            if ($poweroperator === "ltn"):
                $criteria .= "AND (
                       (cards_scry.power IS NOT NULL AND cards_scry.power < ".$power.")
                         OR 
                       (cards_scry.f1_power IS NOT NULL AND cards_scry.f1_power < ".$power.")
                         OR 
                       (cards_scry.f2_power IS NOT NULL AND cards_scry.f2_power < ".$power.")
                         ) ";
            elseif ($poweroperator === "gtr"):
                $criteria .= "AND (
                       (cards_scry.power IS NOT NULL AND cards_scry.power > ".$power.")
                         OR 
                       (cards_scry.f1_power IS NOT NULL AND cards_scry.f1_power > ".$power.")
                         OR 
                       (cards_scry.f2_power IS NOT NULL AND cards_scry.f2_power > ".$power.")
                         ) ";
            elseif ($poweroperator === "eq"):
                $criteria .= "AND (
                       (cards_scry.power IS NOT NULL AND cards_scry.power = ".$power.")
                         OR 
                       (cards_scry.f1_power IS NOT NULL AND cards_scry.f1_power = ".$power.")
                         OR 
                       (cards_scry.f2_power IS NOT NULL AND cards_scry.f2_power = ".$power.")
                         ) ";
            endif;
        endif;
        if (!empty($tough)):
            if ($toughoperator === "ltn"):
                $criteria .= "AND (
                       (cards_scry.toughness IS NOT NULL AND cards_scry.toughness < ".$tough.")
                         OR 
                       (cards_scry.f1_toughness IS NOT NULL AND cards_scry.f1_toughness < ".$tough.")
                         OR 
                       (cards_scry.f2_toughness IS NOT NULL AND cards_scry.f2_toughness < ".$tough.")
                         ) ";
            elseif ($toughoperator === "gtr"):
                $criteria .= "AND (
                       (cards_scry.toughness IS NOT NULL AND cards_scry.toughness > ".$tough.")
                         OR 
                       (cards_scry.f1_toughness IS NOT NULL AND cards_scry.f1_toughness > ".$tough.")
                         OR 
                       (cards_scry.f2_toughness IS NOT NULL AND cards_scry.f2_toughness > ".$tough.")
                         ) ";
            elseif ($toughoperator === "eq"):
                $criteria .= "AND (
                       (cards_scry.toughness IS NOT NULL AND cards_scry.toughness = ".$tough.")
                         OR 
                       (cards_scry.f1_toughness IS NOT NULL AND cards_scry.f1_toughness = ".$tough.")
                         OR 
                       (cards_scry.f2_toughness IS NOT NULL AND cards_scry.f2_toughness = ".$tough.")
                         ) ";    
            endif;
        endif;
        if (!empty($loyalty)):
            if ($loyaltyoperator === "ltn"):
                $criteria .= "AND (
                       (cards_scry.loyalty IS NOT NULL AND cards_scry.loyalty < ".$loyalty.")
                         OR 
                       (cards_scry.f1_loyalty IS NOT NULL AND cards_scry.f1_loyalty < ".$loyalty.")
                         OR 
                       (cards_scry.f2_loyalty IS NOT NULL AND cards_scry.f2_loyalty < ".$loyalty.")
                         ) ";
            elseif ($loyaltyoperator === "gtr"):
                $criteria .= "AND (
                       (cards_scry.loyalty IS NOT NULL AND cards_scry.loyalty > ".$loyalty.")
                         OR 
                       (cards_scry.f1_loyalty IS NOT NULL AND cards_scry.f1_loyalty > ".$loyalty.")
                         OR 
                       (cards_scry.f2_loyalty IS NOT NULL AND cards_scry.f2_loyalty > ".$loyalty.")
                         ) ";
            elseif ($loyaltyoperator === "eq"):
                $criteria .= "AND (
                       (cards_scry.loyalty IS NOT NULL AND cards_scry.loyalty = ".$loyalty.")
                         OR 
                       (cards_scry.f1_loyalty IS NOT NULL AND cards_scry.f1_loyalty = ".$loyalty.")
                         OR 
                       (cards_scry.f2_loyalty IS NOT NULL AND cards_scry.f2_loyalty = ".$loyalty.")
                         ) ";     
            endif;
        endif;

        if ($scope === "mycollection"):
            $criteria .= "AND (($mytable.normal > 0) OR ($mytable.foil > 0) OR ($mytable.etched > 0)) ";
        elseif ($scope === "notcollection"):
            $criteria .= "AND (($mytable.normal = 0 OR $mytable.normal IS NULL) AND ($mytable.foil = 0 OR $mytable.foil IS NULL) AND ($mytable.etched = 0 OR $mytable.etched IS NULL)) ";
        endif;

        if ($legal === 'std'):
            $criteria .= "AND (cards_scry.legalitystandard = 'legal') ";
        endif;

        if ($legal === 'pnr'):
            $criteria .= "AND (cards_scry.legalitypioneer = 'legal') ";
        endif;

        if ($legal === 'mdn'):
            $criteria .= "AND (cards_scry.legalitymodern = 'legal') ";
        endif;

        if ($legal === 'vin'):
            $criteria .= "AND (cards_scry.legalityvintage = 'legal' OR cards_scry.legalityvintage = 'restricted') ";
        endif;

        if ($legal === 'lgc'):
            $criteria .= "AND (cards_scry.legalitylegacy = 'legal' OR cards_scry.legalitylegacy = 'restricted') ";
        endif;

        if ($legal === 'alc'):
            $criteria .= "AND (cards_scry.legalityalchemy = 'legal') ";
        endif;

        if ($legal === 'his'):
            $criteria .= "AND (cards_scry.legalityhistory = 'legal') ";
        endif;

        if ($foilonly === 'yes'):
            $criteria .= "AND ($mytable.foil > 0) AND ($mytable.normal = 0) "; 
        endif;
        if (!empty($setcodesearch)):
            $criteria .= "AND setcode LIKE ? ";
            $params[] = $setcodesearch;
        endif;
        $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": $searchLang",$logfile);
        if (!empty($searchLang) && $searchLang === 'all'):
            // get all
        elseif (!empty($searchLang)):
            $criteria .= "AND lang LIKE ? ";
            $params[] = $searchLang;
        else:
            $criteria .= "AND primary_card = 1 ";
        endif;
        // Sort order
        if (!empty($sortBy)):
            if ($sortBy == "name"):
                $order = "ORDER BY COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "price" AND $scope === "mycollection"):
                $order = "ORDER BY $mytable.topvalue DESC, COALESCE(cards_scry.flavor_name, cards_scry.name), set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "price"):
                $order = "ORDER BY cards_scry.price_sort DESC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "cmc"):
                $order = "ORDER BY cards_scry.cmc ASC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "cmcdown"):
                $order = "ORDER BY cards_scry.cmc DESC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "set"):
                $order = "ORDER BY set_date ASC, cards_scry.set_name ASC, cards_scry.number ASC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC ";
            elseif ($sortBy == "setdown"):
                $order = "ORDER BY set_date DESC, cards_scry.set_name ASC, number ASC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, cs_id ASC ";
            elseif ($sortBy == "setnumberdown"):
                $order = "ORDER BY set_date DESC, cards_scry.set_name ASC, number DESC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, cs_id ASC ";
            elseif ($sortBy == "powerup"):
                $order = "ORDER BY cards_scry.maxpower * 1 ASC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "powerdown"):
                $order = "ORDER BY cards_scry.minpower * 1 DESC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "toughup"):
                $order = "ORDER BY cards_scry.maxtoughness * 1 ASC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            elseif ($sortBy == "toughdown"):
                $order = "ORDER BY cards_scry.mintoughness * 1 DESC, COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            else:
                $order = "ORDER BY COALESCE(cards_scry.flavor_name, cards_scry.name) ASC, set_date DESC, number ASC, cs_id ASC ";
            endif;
        endif;

        $query = $selectAll.$criteria.$order.$sorting;
        $validsearch = "true";
    endif;
endif;