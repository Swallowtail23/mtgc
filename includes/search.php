<?php 
/* Version:     5.1
    Date:       22/01/24
    Name:       search.php
    Purpose:    Layout for search on index.php
    Notes:      
 * 
    1.0
                Initial version
 *  2.0
 *              Added code to get sets from DB instead of setshtml.php
 *  3.0
 *              Add Arena legalities
 * 
 *  4.0         6/12/23
 *              Add year to optgroup
 * 
 *  5.0         02/01/24
 *              Add language search capability             
 *
 *  5.1         22/01/24
 *              Add Automatic search order, with variation for PLST and SLD
 * 
 *  5.2         10/06/24
 *              Add AND / OR to type searches
*/
if (__FILE__ == $_SERVER['PHP_SELF']):
    die('Direct access prohibited');
endif;
?>
<script type="text/javascript"> 
    function SubmitPrep()
        {
            document.body.style.cursor='wait';
        }
</script>
<form action="index.php" method="get">
    <div class="staticpagecontent">
        <div id="grey" class="transparent">
        </div>
        <div id='first_div'>
            <h2 id="h2">Advanced search</h2>
            <input type="hidden" name="complex" value="yes">
            <?php // echo "<input type='hidden' name='collection' value='$collection'>"; 
            echo "<input type='hidden' name='layout' value='$layout'>"; ?>
            <input title="Leave empty for broad search" id='advsearchinput' type="text" name="name" placeholder="Search" autocomplete='off' value="<?php if (isset($qtyresults) AND $qtyresults > 0) { echo $name; }; ?>"><br>
            <input class='stdsubmit' id='advsubmit' type="submit" value='SUBMIT' onclick='SubmitPrep()'><br>
            <span title="Search card names" class="parametersmall checkbox-group">
                <input id='cb1' type="checkbox" class="scopecheckbox checkbox notnotes notability notsetcode notpromo" name="searchname" value="yes" checked="checked">
                <label for='cb1'>
                    <span class="check"></span>
                    <span class="box"></span>Name
                </label>
            </span>
            <span title="Search card types" class="parametersmall checkbox-group">
                <input id='cb2' type="checkbox" class="scopecheckbox checkbox notnotes notability notsetcode notpromo" name="searchtype" value="yes">
                <label for='cb2'><span class="check"></span>
                    <span class="box"></span>Type
                </label>
            </span>
            <span title="Search my notes" class="parametersmall checkbox-group">
                <input id = "yesnotes" type="checkbox" class="scopecheckbox checkbox notability notsetcode notpromo" name="searchnotes" value="yes">
                <label for='yesnotes'>
                    <span class="check"></span>
                    <span class="box"></span>Notes
                </label>
            </span><br>
            <span title="Search setcodes (e.g. 'SOI'")" class="parametersmall checkbox-group">
                <input id='searchsetcode' type="checkbox" class="scopecheckbox checkbox notnotes notability notpromo" name="searchsetcode" value="yes">
                <label for='searchsetcode'><span class="check"></span>
                    <span class="box"></span>Setcode
                </label>
            </span>
            <span title="Search promo types, e.g. 'surgefoil'" class="parametersmall checkbox-group">
                <input id='searchpromo' type="checkbox" class="scopecheckbox checkbox notnotes notability" name="searchpromo" value="yes">
                <label for='searchpromo'><span class="check"></span>
                    <span class="box"></span>Promo
                </label>
            </span>
            <span title="Search recent releases" class="parametersmall checkbox-group">
                <input id='searchnew' type="checkbox" class="scopecheckbox checkbox notnotes" name="searchnew" value="yes">
                <label for='searchnew'><span class="check"></span>
                    <span class="box"></span>New (7d)
                </label>
            </span>
            <br>Abilities:<br>
            <span class="parametermed checkbox-group">
                <input id='abilityall' type="checkbox" class="scopecheckbox checkbox notnotes notsetcode notpromo" name="searchability" value="yes">
                <label for='abilityall'>
                    <span class="check"></span>
                    <span class="box"></span>fuzzy 
                </label>
            </span>
            <span class="parametersmall checkbox-group">
                <input id='abilityexact' type="checkbox" class="scopecheckbox checkbox notnotes notsetcode notpromo" name="searchabilityexact" value="yes">
                <label for='abilityexact'>
                    <span class="check"></span>
                    <span class="box"></span>exact 
                </label>
            </span>
            <br>
            <h4 class="h4">Search scope:</h4>
            <span class="parametersmall">
                <label class="radio"><input type="radio" name="scope" value="all" checked="checked"><span class="outer"><span class="inner"></span></span>All cards</label>
            </span>
            <span title="Only show my cards" class="parametersmall">
                <label class="radio"><input type="radio" name="scope" value="mycollection"><span class="outer"><span class="inner"></span></span>Collection</label>
            </span>
            <span title="Only show cards I don't have" class="parametersmall">
                <label class="radio"><input type="radio" name="scope" value="notcollection"><span class="outer"><span class="inner"></span></span>Missing</label>
            </span>
            <br>
            <h4 class="h4">Set:</h4> Ctrl+click to select multiple sets:<br>
            <select class='setselect' size="15" multiple name="set[]">
                <?php 
                $result = $db->query(
                       'SELECT 
                            name AS set_name,
                            code AS setcode,
                            min(release_date) as date,
                            parent_set_code
                        FROM sets
                        GROUP BY 
                            name
                        ORDER BY 
                            release_date DESC, parent_set_code DESC');
                if ($result === false):
                    trigger_error("[ERROR] search.php: Sets list: Error: " . $db->error, E_USER_ERROR);
                else:
                    $currentblock = $currentyear = null;
                    while ($row = $result->fetch_assoc()):
                        if(isset($row['setcode']) AND $row['setcode'] !== null):
                            $set_upper = strtoupper($row['setcode']);
                        else:
                            $set_upper = '';
                        endif;
                        if(isset($row['parent_set_code']) AND $row['parent_set_code'] !== null):
                            $parent_set_upper = strtoupper($row['parent_set_code']);
                        else:
                            $parent_set_upper = '';
                        endif;
                        if(isset($row['date']) AND $row['date'] !== null):
                            $rowyear = date('Y', strtotime($row['date']));
                        else:
                            $rowyear = '';
                        endif;
                        if( $currentyear == null || $currentyear != $rowyear):
                            if($currentyear != null):
                                echo "</optgroup>\n";
                            endif;
                            echo "<optgroup class='optgroup' label='$rowyear' style='color: #3F51B5; font-weight: bold; font-style: italic;'>\n";
                            $currentyear = $rowyear;
                        endif;
                        if( $currentblock == null || $parent_set_upper != $currentblock ):
                            if( $currentblock != null ):
                                echo "</optgroup\n>";
                            endif;
                            echo "<optgroup class='optgroup' label='&nbsp;&nbsp;&nbsp;&nbsp;$parent_set_upper' style='color: #3F51B5; font-style: italic;'>\n";
                            $currentblock = $parent_set_upper;
                        endif;
                        echo "<option title='{$row['set_name']}' value='{$row['setcode']}' style='color: rgba(0,0,0,0.77); font-style: normal;'>&nbsp;&nbsp;&nbsp;&nbsp;$set_upper: {$row['set_name']}</option>\n";
                    endwhile;
                endif;    
                if( $currentblock != null ) echo "</optgroup>\n";
                ?>
            </select>
            <br><br>
            
            <h4 class="h4Sortby">Sort by</h4>
            <label class="radio"><input type="radio" name="sortBy" value="auto" checked="checked"><span class="outer"><span class="inner"></span></span>Automatic</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="set"><span class="outer"><span class="inner"></span></span>Set &#x25B2;/ Number &#x25B2;</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="setdown"><span class="outer"><span class="inner"></span></span>Set &#x25BC;/ Number &#x25B2;</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="setnumberdown"><span class="outer"><span class="inner"></span></span>Set &#x25BC;/ Number &#x25BC;</label><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="name"><span class="outer"><span class="inner"></span></span>Name</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="price"><span class="outer"><span class="inner"></span></span>Price &#x25BC;</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="cmc"><span class="outer"><span class="inner"></span></span>Mana value &#x25B2;</label>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="cmcdown"><span class="outer"><span class="inner"></span></span>Mana value &#x25BC; </label></span><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="powerup"><span class="outer"><span class="inner"></span></span>Power &#x25B2;</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="powerdown"><span class="outer"><span class="inner"></span></span>Power &#x25BC;</label><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="toughup"><span class="outer"><span class="inner"></span></span>Toughness &#x25B2;</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="toughdown"><span class="outer"><span class="inner"></span></span>Toughness &#x25BC;</label>
            
            <h4 class="h4">Game type</h4>
            <span class="parametersmall">
                <label class="radio"><input type="radio" name="gametypeOp" value="AND" checked="checked"><span class="outer"><span class="inner"></span></span>AND</label>
            </span>
            <span class="parametersmall">
                <label class="radio"><input type="radio" name="gametypeOp" value="OR"><span class="outer"><span class="inner"></span></span>OR</label>
            </span>
            <span class="checkbox-group">
                <input id='cb26' type="checkbox" class="checkbox" name="gametypeExcl" value="ONLY">
                <label for='cb26'>
                    <span class="check"></span>
                    <span class="box"></span>ONLY
                </label>
            </span><br>
            <span class="parametermed checkbox-group">
                <input id='cb27' type="checkbox" class="checkbox" name="paper" value="yes" checked>
                <label for='cb27'>
                    <span class="check"></span>
                    <span class="box"></span>Paper
                </label>
            </span>
            <span class="parametermed checkbox-group">
                <input id='cb28' type="checkbox" class="checkbox" name="arena" value="yes">
                <label for='cb28'>
                    <span class="check"></span>
                    <span class="box"></span>MtG Arena
                </label>
            </span><br>
            <span class="parametermed checkbox-group">
                <input id='cb29' type="checkbox" class="checkbox" name="online" value="yes">
                <label for='cb29'>
                    <span class="check"></span>
                    <span class="box"></span>MtG Online
                </label>
            </span>
            <br>
        </div>
        <div id="second_div">
            <div>
                <h4 class="h4">Legality</h4>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="any" checked><span class="outer"><span class="inner"></span></span>Any</label></span>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="std"><span class="outer"><span class="inner"></span></span>Standard</label></span>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="pnr"><span class="outer"><span class="inner"></span></span>Pioneer</label></span><br>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="mdn"><span class="outer"><span class="inner"></span></span>Modern</label></span>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="vin"><span class="outer"><span class="inner"></span></span>Vintage</label></span>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="lgc"><span class="outer"><span class="inner"></span></span>Legacy</label></span><br>
                <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="alc"><span class="outer"><span class="inner"></span></span>Alchemy</label></span>
                <label class="radio"><input type="radio" name="legal" value="his"><span class="outer"><span class="inner"></span></span>Historic</label>

                <h4 class="h4">Colour</h4>
                <span class="parametersmall">
                    <label class="radio"><input type="radio" name="colourOp" value="AND" checked="checked"><span class="outer"><span class="inner"></span></span>AND</label>
                </span>
                <span class="parametersmall">
                    <label class="radio"><input type="radio" name="colourOp" value="OR"><span class="outer"><span class="inner"></span></span>OR</label>
                </span>
                <span class="checkbox-group">
                    <input id='cb4' type="checkbox" class="checkbox" name="colourExcl" value="ONLY">
                    <label for='cb4'>
                        <span class="check"></span>
                        <span class="box"></span>ONLY
                    </label>
                </span><br>
                <span class="parametersmall checkbox-group">
                    <input id='cb5' type="checkbox" class="checkbox" name="white" value="yes">
                    <label for='cb5'>
                        <span class="check"></span>
                        <span class="box"></span>White
                    </label>
                </span>
                <span class="parametersmall checkbox-group">
                    <input id='cb6' type="checkbox" class="checkbox" name="blue" value="yes">
                    <label for='cb6'>
                        <span class="check"></span>
                        <span class="box"></span>Blue
                    </label>
                </span>
                <span class="parametersmall checkbox-group">
                    <input id='cb7' type="checkbox" class="checkbox" name="black" value="yes">
                    <label for='cb7'>
                        <span class="check"></span>
                        <span class="box"></span>Black
                    </label>
                </span><br>
                <span class="parametersmall checkbox-group">
                    <input id='cb8' type="checkbox" class="checkbox" name="red" value="yes">
                    <label for='cb8'>
                        <span class="check"></span>
                        <span class="box"></span>Red
                    </label>
                </span>
                <span class="parametersmall checkbox-group">
                    <input id='cb9' type="checkbox" class="checkbox" name="green" value="yes">
                    <label for='cb9'>
                        <span class="check"></span>
                        <span class="box"></span>Green
                    </label>
                </span>
                <span class="parametersmall checkbox-group">
                    <input id='cb10' type="checkbox" class="checkbox" name="colourless" value="yes">
                    <label for='cb10'>
                        <span class="check"></span>
                        <span class="box"></span>Colourless
                    </label>
                </span><br>
                <h4 class="h4">Rarity</h4>
                <span class="parametermed checkbox-group">
                    <input id='cb11' type="checkbox" class="checkbox" name="common" value="yes">
                    <label for='cb11'>
                        <span class="check"></span>
                        <span class="box"></span>Common
                    </label>
                </span>
                <span class="checkbox-group">
                    <input id='cb12' type="checkbox" class="checkbox" name="uncommon" value="yes">
                    <label for='cb12'>
                        <span class="check"></span>
                        <span class="box"></span>Uncommon
                    </label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb13' type="checkbox" class="checkbox" name="rare" value="yes">
                    <label for='cb13'>
                        <span class="check"></span>
                        <span class="box"></span>Rare
                    </label>
                </span>
                <span class="checkbox-group">
                    <input id='cb14' type="checkbox" class="checkbox" name="mythic" value="yes">
                    <label for='cb14'>
                        <span class="check"></span>
                        <span class="box"></span>Mythic rare
                    </label>
                </span><br>
                <h4 class="h4">Type</h4>
                <span class="parametersmall">
                    <label class="radio"><input type="radio" name="typeOp" value="AND"><span class="outer"><span class="inner"></span></span>AND</label>
                </span>
                <span class="parametersmall">
                    <label class="radio"><input type="radio" name="typeOp" value="OR" checked="checked"><span class="outer"><span class="inner"></span></span>OR</label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb15' type="checkbox" class="checkbox" name="instant" value="yes">
                    <label for='cb15'>
                        <span class="check"></span>
                        <span class="box"></span>Instant
                    </label>
                </span>
                <span class="parametermed checkbox-group">
                    <input id='cb16' type="checkbox" class="checkbox" name="enchantment" value="yes">
                    <label for='cb16'>
                        <span class="check"></span>
                        <span class="box"></span>Enchantment
                    </label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb17' type="checkbox" class="checkbox" name="sorcery" value="yes">
                    <label for='cb17'>
                        <span class="check"></span>
                        <span class="box"></span>Sorcery
                    </label>
                </span>
                <span class="parametermed checkbox-group">
                    <input id='cb18' type="checkbox" class="checkbox" name="creature" value="yes">
                    <label for='cb18'>
                        <span class="check"></span>
                        <span class="box"></span>Creature
                    </label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb19' type="checkbox" class="checkbox" name="planeswalker" value="yes">
                    <label for='cb19'>
                        <span class="check"></span>
                        <span class="box"></span>Planeswalker
                    </label>
                </span>
                <span class="parametermed checkbox-group">
                    <input id='cb20' type="checkbox" class="checkbox" name="legendary" value="yes">
                    <label for='cb20'>
                        <span class="check"></span>
                        <span class="box"></span>Legendary
                    </label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb21' type="checkbox" class="checkbox" name="artifact" value="yes">
                    <label for='cb21'>
                        <span class="check"></span>
                        <span class="box"></span>Artifact
                    </label>
                </span>
                <span class="parametermed checkbox-group">
                    <input id='cb22' type="checkbox" class="checkbox" name="tribal" value="yes">
                    <label for='cb22'>
                        <span class="check"></span>
                        <span class="box"></span>Kindred
                    </label>
                </span><br>
                <span class="parametermed checkbox-group">
                    <input id='cb23' type="checkbox" class="checkbox" name="land" value="yes">
                    <label for='cb23'>
                        <span class="check"></span>
                        <span class="box"></span>Land
                    </label>
                </span>
                <span class="parametermed checkbox-group">
                    <input id='cb24' type="checkbox" class="checkbox" name="token" value="yes">
                    <label for='cb24'>
                        <span class="check"></span>
                        <span class="box"></span>Token
                    </label>
                </span><br>
                <span class="checkbox-group">
                    <input id='cb25' type="checkbox" class="checkbox" name="battle" value="yes">
                    <label for='cb25'>
                        <span class="check"></span>
                        <span class="box"></span>Battle
                    </label>
                </span>
            </div>
            <h4 class="h4">Typal</h4>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="merfolk"><span class="outer"><span class="inner"></span></span>Merfolk</label></span>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="goblin"><span class="outer"><span class="inner"></span></span>Goblin</label></span>
            <label class="radio"><input type="radio" name="tribe" value="treefolk"><span class="outer"><span class="inner"></span></span>Treefolk</label><br>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="elf"><span class="outer"><span class="inner"></span></span>Elf</label></span>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="vampire"><span class="outer"><span class="inner"></span></span>Vampire</label></span>
            <label class="radio"><input type="radio" name="tribe" value="sliver"><span class="outer"><span class="inner"></span></span>Sliver</label><br>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="human"><span class="outer"><span class="inner"></span></span>Human</label></span>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="spider"><span class="outer"><span class="inner"></span></span>Spider</label></span>
            <label class="radio"><input type="radio" name="tribe" value="zombie"><span class="outer"><span class="inner"></span></span>Zombie</label>
            
            <h4 class="h4">Language</h4>
            <select class="dropdown" name='lang' id='langSelect'> 
                <option value='default' selected>Default</option><?php 
                foreach($search_langs as $lang): ?>
                    <option value='<?php echo $lang['code']; ?>'>
                        <?php echo $lang['pretty']; ?>
                    </option> <?php 
                endforeach; ?>
                <option value='all'>All languages</option>
            </select>
            
            <h4 class="h4">Power / Toughness / Loyalty / Mana value</h4>
            Power<br>
            <select class="dropdown" name="poweroperator">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="ltn">Less than</option>
                <option value="eq">Equal to</option>
                <option value="gtr">Greater than</option>
            </select>
            <select class="dropdown" name="power">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="20">20</option>
            </select>
            <br>Toughness<br>
            <select class="dropdown" name="toughoperator">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="ltn">Less than</option>
                <option value="eq">Equal to</option>
                <option value="gtr">Greater than</option>
            </select>
            <select class="dropdown" name="tough">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="20">20</option>
            </select> 
            <br>Loyalty<br>
            <select class="dropdown" name="loyaltyoperator">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="ltn">Less than</option>
                <option value="eq">Equal to</option>
                <option value="gtr">Greater than</option>
            </select>
            <select class="dropdown" name="loyalty">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
            </select>  
            <br>Mana value<br>
            <select class="dropdown" name="cmcoperator">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="ltn">Less than</option>
                <option value="eq">Equal to</option>
                <option value="gtr">Greater than</option>
            </select>
            <select class="dropdown" name="cmcvalue">
                <option disabled selected style='display:none;'>&nbsp;</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
                <option value="8">8</option>
                <option value="9">9</option>
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="20">20</option>
            </select>             
            <br>&nbsp;<br>
        </div>
    </div>
</form>
<?php ?>