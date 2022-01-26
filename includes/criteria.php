<?php
/* Version:     2.0
    Date:       26/01/22
    Name:       criteria.php
    Purpose:    PHP script to build search criteria
    Notes:      
 * 
    1.0
                Initial version
    2.0
                Cards_scry refactoring
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

if (empty($_GET)) :
    $validsearch = "";
elseif (!$adv == "yes" ) :
    // Not an advanced search called
    if (strlen($name) > 2): // Needs to have more than 2 characters to search
        if ($exact === "yes"):
            $criteria = "cards_scry.name LIKE '$name' OR cards_scry.f1_name LIKE '$name' OR cards_scry.f2_name LIKE '$name' ";
        else:
            $criteria = "cards_scry.name LIKE '%$name%' ";
        endif;
        $order = "ORDER BY cards_scry.name ASC ";
        $query = $selectAll.$criteria.$order.$sorting;
        $validsearch = "true";
    else: 
        // Not enough characters - set as a not valid search
        $qtyresults = 0;
        $validsearch = "false";
    endif;
elseif ($adv == "yes" ) :
    // An advanced search called
    $criteriaNTA = "";
    if ($searchnotes === "yes"):
            $criteriaNTA = "$mytable.notes LIKE '%$name%' ";
    elseif (empty($name) AND (empty($searchname) AND empty($searchtype) AND empty($searchability) AND empty($searchabilityexact))):
        $criteriaNTA .= "cards_scry.name LIKE '%%' ";
    elseif (empty($searchname) AND empty($searchtype) AND empty($searchability) AND empty($searchabilityexact)):
        $criteriaNTA .= "cards_scry.name LIKE '%$name%' ";
    else:
        if ($searchname === "yes"):
            if ($exact === "yes"):
                $criteriaNTA = "cards_scry.name LIKE '$name' OR cards_scry.f1_name LIKE '$name' OR cards_scry.f2_name LIKE '$name'";
            else:
                $criteriaNTA = "cards_scry.name LIKE '%$name%' ";
            endif;
        endif;
        if ($searchtype === "yes"):
            if (!empty($criteriaNTA)) :
                $criteriaNTA .= "OR ";
            endif;
            $criteriaNTA .= "cards_scry.type LIKE '%$name%' ";
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
            $criteriaNTA .= "MATCH (cards_scry.ability,cards_scry.f1_ability,cards_scry.f2_ability) AGAINST ('$abilitytext' IN BOOLEAN MODE) ";
        elseif ($searchabilityexact === "yes"):
            if (!empty($criteriaNTA)) :
                $criteriaNTA .= "OR ";
            endif;
            $criteriaNTA .= "cards_scry.ability LIKE '%$name%' ";
        endif;
    endif;
    $criteria = "(".$criteriaNTA.") ";
    // Colours first
    $criteriaCol = "";
    if ($white === "yes"):
        $criteriaCol = "cards_scry.color LIKE '%W%' ";
    endif;
    if ($blue === "yes"):
        if (!empty($criteriaCol)) :
            $criteriaCol .= $colourOp." ";
        endif;
        $criteriaCol .= "cards_scry.color LIKE '%U%' ";
    endif;
    if ($black === "yes"):
        if (!empty($criteriaCol)) :
            $criteriaCol .= $colourOp." ";
        endif;
        $criteriaCol .= "cards_scry.color LIKE '%B%' ";
    endif;
    if ($red === "yes"):
        if (!empty($criteriaCol)) :
            $criteriaCol .= $colourOp." ";
        endif;
        $criteriaCol .= "cards_scry.color LIKE '%R%' ";
    endif;
    if ($green === "yes"):
        if (!empty($criteriaCol)) :
            $criteriaCol .= $colourOp." ";
        endif;
        $criteriaCol .= "cards_scry.color LIKE '%G%' ";
    endif;
    if ($colourless === "yes"):
        if (!empty($criteriaCol)) :
            $criteriaCol .= $colourOp." ";
        endif;
        $criteriaCol .= "cards_scry.color LIKE '%C%' ";
    endif;
    if (!empty($criteriaCol)) :
        $criteria .= "AND (".$criteriaCol.") ";
    endif;
    // Colour exclusivity?
    if ($colourExcl == "ONLY"):
        if (empty($white)):
            $criteria .= "AND (cards_scry.color NOT LIKE '%W%') ";
        endif;
        if (empty($blue)):
            $criteria .= "AND (cards_scry.color NOT LIKE '%U%') ";
        endif;
        if (empty($red)):
            $criteria .= "AND (cards_scry.color NOT LIKE '%R%') ";
        endif;
        if (empty($green)):
            $criteria .= "AND (cards_scry.color NOT LIKE '%G%') ";
        endif;
        if (empty($black)):
            $criteria .= "AND (cards_scry.color NOT LIKE '%B%') ";
        endif;
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
        $criteriaType .= "cards_scry.type LIKE '%tribal%' ";
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
    if (!empty($criteriaType)) :
        $criteria .= "AND (".$criteriaType.") ";
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
    if ($tribe === "centaur"):
        if (!empty($criteriaTribe)) :
            $criteriaTribe .= "OR ";
        endif;
        $criteriaTribe .= "cards_scry.type LIKE '%centaur%' ";
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
        $criteria .= "AND (($mytable.normal > 0) OR ($mytable.foil > 0)) ";
    endif;
    
    if ($legal === 'std'):
        $criteria .= "AND (cards_scry.legalitystandard = 'legal') ";
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
      
    if ($foilonly === 'foilonly'):
        $criteria .= "AND ($mytable.foil > 0) AND ($mytable.normal = 0) "; 
    endif;
    
    // Sort order
    if (!empty($sortBy)):
        if ($sortBy == "name"):
            $order = "ORDER BY cards_scry.name ASC ";
        elseif ($sortBy == "price" AND $scope === "mycollection"):
            $order = "ORDER BY cards_scry.price DESC ";
        elseif ($sortBy == "price"):
            $order = "ORDER BY cards_scry.price DESC ";
        elseif ($sortBy == "cmc"):
            $order = "ORDER BY cards_scry.cmc ASC ";
        elseif ($sortBy == "cmcdown"):
            $order = "ORDER BY cards_scry.cmc DESC ";
        elseif ($sortBy == "set"):
            $order = "ORDER BY cards_scry.release_date ASC, cards_scry.set_name ASC, cards_scry.number ASC, cards_scry.cmc DESC ";
        elseif ($sortBy == "setdown"):
            $order = "ORDER BY cards_scry.release_date DESC, cards_scry.set_name ASC, cards_scry.number ASC, cards_scry.cmc DESC ";
        elseif ($sortBy == "powerup"):
            $order = "ORDER BY cards_scry.maxpower * 1 ASC ";
        elseif ($sortBy == "powerdown"):
            $order = "ORDER BY cards_scry.minpower * 1 DESC ";
        elseif ($sortBy == "toughup"):
            $order = "ORDER BY cards_scry.maxtoughness * 1 ASC ";
        elseif ($sortBy == "toughdown"):
            $order = "ORDER BY cards_scry.mintoughness * 1 DESC ";        
        else:
            $order = "ORDER BY cards_scry.name ASC ";
        endif;
    endif;
        
    $query = $selectAll.$criteria.$order.$sorting;
    $validsearch = "true";
endif;
