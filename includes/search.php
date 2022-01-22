<?php 
/* Version:     2.0
    Date:       23/01/17
    Name:       search.php
    Purpose:    Layout for search on index.php
    MySQLi:     Yes
    Notes:      
 * 
    1.0
                Initial version
 *  2.0
 *              Added code to get sets from DB instead of setshtml.php
*/
if (__FILE__ == $_SERVER['PHP_SELF']):
    die('Direct access prohibited');
endif;
?>

<form action="index.php" method="get">
    <div class="staticpagecontent">   
        <div id="grey" class="transparent">
        </div>
        <div id='first_div'>
            <h2 id="h2">Advanced search</h2>
            <input type="hidden" name="adv" value="yes">
            <?php // echo "<input type='hidden' name='collection' value='$collection'>"; 
            echo "<input type='hidden' name='layout' value='$layout'>"; ?>
            <input id='advsearchinput' type="text" name="name" placeholder="Search" autocomplete='off' value="<?php echo $name ; ?>"><br>
            <input class='stdsubmit' id='advsubmit' type="submit" value='SUBMIT'><br>
            <span class="parametermed checkbox-group">
                <input id='cb1' type="checkbox" class="checkbox notnotes" name="searchname" value="yes" checked="checked">
                <label for='cb1'>
                    <span class="check"></span>
                    <span class="box"></span>Name
                </label>
            </span>
            <span class="parametersmall checkbox-group">
                <input id='cb2' type="checkbox" class="checkbox notnotes" name="searchtype" value="yes">
                <label for='cb2'><span class="check"></span>
                    <span class="box"></span>Type
                </label>
            </span>
            <br>Abilities:<br>
            <span class="parametermed checkbox-group">
                <input id='abilityall' type="checkbox" class="checkbox notnotes" name="searchability" value="yes">
                <label for='abilityall'>
                    <span class="check"></span>
                    <span class="box"></span>fuzzy 
                </label>
            </span>
            <span class="parametersmall checkbox-group">
                <input id='abilityexact' type="checkbox" class="checkbox notnotes" name="searchabilityexact" value="yes">
                <label for='abilityexact'>
                    <span class="check"></span>
                    <span class="box"></span>exact 
                </label>
            </span><br>
            <span class="parameterlarge checkbox-group">
                <input id='cb3' type="checkbox" class="checkbox notnotes" name="scope" value="mycollection">
                <label for='cb3'>
                    <span class="check"></span>
                    <span class="box"></span>My collection only 
                </label>
            </span><br>
            <span class="parameterlarge checkbox-group">
                <input type="checkbox" class="checkbox" id = "yesnotes" name="searchnotes" value="yes">
                <label for='yesnotes'>
                    <span class="check"></span>
                    <span class="box"></span>Search notes only
                </label>
            </span><br>
            
            <h4 class="h4">Legality</h4>
            <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="any" checked><span class="outer"><span class="inner"></span></span>Any</label></span><br>
            <label class="radio"><input type="radio" name="legal" value="std"><span class="outer"><span class="inner"></span></span>Standard</label>
            <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="mdn"><span class="outer"><span class="inner"></span></span>Modern</label></span><br>
            <span class="parametersmall"><label class="radio"><input type="radio" name="legal" value="vin"><span class="outer"><span class="inner"></span></span>Vintage</label></span>
            <label class="radio"><input type="radio" name="legal" value="lgc"><span class="outer"><span class="inner"></span></span>Legacy</label>
            <h4 class="h4">Colour search criteria</h4>
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
            <h4 class="h4">Set:</h4> Ctrl+click to select multiple sets:<br>
            <select class='setselect' size="8" multiple name="set[]">
                <?php 
                $result = $db->query('SELECT fullsetname,block,setcodeid from sets 
                    LEFT JOIN blockSequence on sets.block = blockSequence.blockname 
                    ORDER BY sequence DESC,releasedat DESC');
                if ($result === false):
                    trigger_error("[ERROR] search.php: Sets list: Error: " . $db->error, E_USER_ERROR);
                else:
                    $currentblock = null;
                    while ($row = $result->fetch_assoc()):
                        if( $currentblock == null || $row['block'] != $currentblock ):
                            if( $currentblock != null ):
                                echo "</optgroup\n>";
                            endif;
                            echo "<optgroup class='optgroup' label='{$row['block']}'>\n";
                            $currentblock = $row['block'];
                            // $data = array('blockname' => $row['block']);
                            // $db->insert('blockSequence',$data);
                        endif;
                        echo "<option value='{$row['setcodeid']}'>{$row['fullsetname']}</option>\n";
                    endwhile;
                endif;    
                if( $currentblock != null ) echo "</optgroup>\n";
                ?>
            </select>
        </div>
        <div id="second_div">
            &nbsp;<h4 class="h4Sortby">Sort by</h4>
            <label class="radio"><input type="radio" name="sortBy" value="set"><span class="outer"><span class="inner"></span></span>Set &#x25B2;/ Number &#x25B2;</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="setdown" checked="checked"><span class="outer"><span class="inner"></span></span>Set &#x25BC;/ Number &#x25B2;</label><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="name"><span class="outer"><span class="inner"></span></span>Name</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="price"><span class="outer"><span class="inner"></span></span>Price &#x25BC;</label><br>
            <label class="radio"><input type="radio" name="sortBy" value="cmc"><span class="outer"><span class="inner"></span></span>CMC &#x25B2;</label>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="cmcdown"><span class="outer"><span class="inner"></span></span>CMC &#x25BC; </label></span><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="powerup"><span class="outer"><span class="inner"></span></span>Power &#x25B2;</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="powerdown"><span class="outer"><span class="inner"></span></span>Power &#x25BC;</label><br>
            <span class="parametermed"><label class="radio"><input type="radio" name="sortBy" value="toughup"><span class="outer"><span class="inner"></span></span>Tough &#x25B2;</label></span>
            <label class="radio"><input type="radio" name="sortBy" value="toughdown"><span class="outer"><span class="inner"></span></span>Tough &#x25BC;</label>
            <div>
            <h4 class="h4">Type</h4>
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
                    <span class="box"></span>Tribal
                </label>
            </span><br>
            <span class="parametersmall checkbox-group">
                <input id='cb23' type="checkbox" class="checkbox" name="land" value="yes">
                <label for='cb23'>
                    <span class="check"></span>
                    <span class="box"></span>Land
                </label>
            </span>
            <!--<span class="checkbox-group">
                <input id='cb24' type="checkbox" class="checkbox" name="flipcard" value="yes">
                <label for='cb24'>
                    <span class="check"></span>
                    <span class="box"></span>Double sided card
                </label>
            </span> --> </div><br>
            <h4 class="h4">Tribe</h4>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="merfolk"><span class="outer"><span class="inner"></span></span>Merfolk</label></span>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="goblin"><span class="outer"><span class="inner"></span></span>Goblin</label></span>
            <label class="radio"><input type="radio" name="tribe" value="treefolk"><span class="outer"><span class="inner"></span></span>Treefolk</label><br>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="centaur"><span class="outer"><span class="inner"></span></span>Centaur</label></span>
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="vampire"><span class="outer"><span class="inner"></span></span>Vampire</label></span>
            <label class="radio"><input type="radio" name="tribe" value="sliver"><span class="outer"><span class="inner"></span></span>Sliver</label><br>                         
            <span class="parametersmall"><label class="radio"><input type="radio" name="tribe" value="human"><span class="outer"><span class="inner"></span></span>Human</label></span>        
            <label class="radio"><input type="radio" name="tribe" value="zombie"><span class="outer"><span class="inner"></span></span>Zombie</label>  
            <h4 class="h4">Power / Toughness / Loyalty / CMC</h4>
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
            <br>CMC<br>
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