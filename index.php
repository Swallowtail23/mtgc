<?php
/* Version:     5.0
    Date:       25/01/2022
    Name:       index.php
    Purpose:    Main site page
    Notes:       
    To do:      
    
    1.0
                Initial version
 *  2.0     
 *              Moved image calls to use scryfall function
 *  3.0         
 *              Moved from writelog to Message class
 *  4.0
 *              Moved to mysqli
 *  5.0
 *              Re-factoring for cards_scry
 *              Javascript simplification and Ajax changes
*/

//Call script initiation mechs
session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                                  //Check if user is disabled or needs to change password
// Change these to alter default numbers per page
$listperpage = 30;
$gridperpage = 30;
$bulkperpage = 100;

// Define layout and results per page for each layout type
if (isset($_GET['layout'])):
    $layout = filter_input(INPUT_GET, 'layout', FILTER_SANITIZE_STRING);
    if ($layout == 'grid'):
        $perpage = $gridperpage;
    elseif ($layout == 'list'):
        $perpage = $listperpage;
    elseif ($layout == 'bulk'):
        $perpage = $bulkperpage;
    else:
        $layout = 'grid';
        $perpage = $gridperpage;
    endif;
else:
    $layout = 'grid'; //default to grid if not specified
    $perpage = $gridperpage;
endif;

// Set up all the stuff we need and filter GET variables
if (isset($_GET["page"])) :
    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_STRING);
else :
    $page = 1;
endif;
$start_from = ($page - 1) * $perpage;
if (isset($_GET['name']) AND $_GET['name'] !== ""):
    $nameget = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name in GET is $nameget",$logfile);
    $nametrim = trim($nameget, " \t\n\r\0\x0B");
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name in nametrim is $nametrim",$logfile);
    $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
    $name = preg_replace($regex, ' ', $nametrim);
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name sent to sql escape is $name",$logfile);
    $name = $db->escape($name);
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name in name is $name",$logfile);
else:
    $name = '';
endif;
$searchname = isset($_GET['searchname']) ? filter_input(INPUT_GET, 'searchname', FILTER_SANITIZE_STRING):'';
$searchtype = isset($_GET['searchtype']) ? filter_input(INPUT_GET, 'searchtype', FILTER_SANITIZE_STRING):'';
$searchability = isset($_GET['searchability']) ? filter_input(INPUT_GET, 'searchability', FILTER_SANITIZE_STRING):'';
$searchabilityexact = isset($_GET['searchabilityexact']) ? filter_input(INPUT_GET, 'searchabilityexact', FILTER_SANITIZE_STRING):'';
$searchnotes = isset($_GET['searchnotes']) ? filter_input(INPUT_GET, 'searchnotes', FILTER_SANITIZE_STRING):'';
$white = isset($_GET['white']) ? filter_input(INPUT_GET, 'white', FILTER_SANITIZE_STRING):'';
$blue = isset($_GET['blue']) ? filter_input(INPUT_GET, 'blue', FILTER_SANITIZE_STRING):'';
$black = isset($_GET['black']) ? filter_input(INPUT_GET, 'black', FILTER_SANITIZE_STRING):'';
$red = isset($_GET['red']) ? filter_input(INPUT_GET, 'red', FILTER_SANITIZE_STRING):'';
$green = isset($_GET['green']) ? filter_input(INPUT_GET, 'green', FILTER_SANITIZE_STRING):'';
$artifact = isset($_GET['artifact']) ? filter_input(INPUT_GET, 'artifact', FILTER_SANITIZE_STRING):'';
$colourless = isset($_GET['colourless']) ? filter_input(INPUT_GET, 'colourless', FILTER_SANITIZE_STRING):'';
$land = isset($_GET['land']) ? filter_input(INPUT_GET, 'land', FILTER_SANITIZE_STRING):'';
$colourOp = isset($_GET['colourOp']) ? filter_input(INPUT_GET, 'colourOp', FILTER_SANITIZE_STRING):'';
$colourExcl = isset($_GET['colourExcl']) ? filter_input(INPUT_GET, 'colourExcl', FILTER_SANITIZE_STRING):'';
$common = isset($_GET['common']) ? filter_input(INPUT_GET, 'common', FILTER_SANITIZE_STRING):'';
$uncommon = isset($_GET['uncommon']) ? filter_input(INPUT_GET, 'uncommon', FILTER_SANITIZE_STRING):'';
$rare = isset($_GET['rare']) ? filter_input(INPUT_GET, 'rare', FILTER_SANITIZE_STRING):'';
$mythic = isset($_GET['mythic']) ? filter_input(INPUT_GET, 'mythic', FILTER_SANITIZE_STRING):'';
$creature = isset($_GET['creature']) ? filter_input(INPUT_GET, 'creature', FILTER_SANITIZE_STRING):'';
$instant = isset($_GET['instant']) ? filter_input(INPUT_GET, 'instant', FILTER_SANITIZE_STRING):'';
$sorcery = isset($_GET['sorcery']) ? filter_input(INPUT_GET, 'sorcery', FILTER_SANITIZE_STRING):'';
$enchantment = isset($_GET['enchantment']) ? filter_input(INPUT_GET, 'enchantment', FILTER_SANITIZE_STRING):'';
$planeswalker = isset($_GET['planeswalker']) ? filter_input(INPUT_GET, 'planeswalker', FILTER_SANITIZE_STRING):'';
$tribal = isset($_GET['tribal']) ? filter_input(INPUT_GET, 'tribal', FILTER_SANITIZE_STRING):'';
$tribe = isset($_GET['tribe']) ? filter_input(INPUT_GET, 'tribe', FILTER_SANITIZE_STRING):'';
$legendary = isset($_GET['legendary']) ? filter_input(INPUT_GET, 'legendary', FILTER_SANITIZE_STRING):'';
$rareOp = isset($_GET['rareOp']) ? filter_input(INPUT_GET, 'rareOp', FILTER_SANITIZE_STRING):'';
$exact = isset($_GET['exact']) ? filter_input(INPUT_GET, 'exact', FILTER_SANITIZE_STRING):'';
if ((isset($_GET['set'])) AND ( is_array($_GET['set']))):
    $selectedSets = filter_var_array($_GET['set'], FILTER_SANITIZE_STRING);
endif;
$sortBy = isset($_GET['sortBy']) ? filter_input(INPUT_GET, 'sortBy', FILTER_SANITIZE_STRING):'';
$poweroperator = isset($_GET['poweroperator']) ? filter_input(INPUT_GET, 'poweroperator', FILTER_SANITIZE_STRING):'';
$toughoperator = isset($_GET['toughoperator']) ? filter_input(INPUT_GET, 'toughoperator', FILTER_SANITIZE_STRING):'';
$loyaltyoperator = isset($_GET['loyaltyoperator']) ? filter_input(INPUT_GET, 'loyaltyoperator', FILTER_SANITIZE_STRING):'';
$cmcoperator = isset($_GET['cmcoperator']) ? filter_input(INPUT_GET, 'cmcoperator', FILTER_SANITIZE_STRING):'';
$cmcvalue = isset($_GET['cmcvalue']) ? filter_input(INPUT_GET, 'cmcvalue', FILTER_SANITIZE_STRING):'';
$power = isset($_GET['power']) ? filter_input(INPUT_GET, 'power', FILTER_SANITIZE_STRING):'';
$tough = isset($_GET['tough']) ? filter_input(INPUT_GET, 'tough', FILTER_SANITIZE_STRING):'';
$loyalty = isset($_GET['loyalty']) ? filter_input(INPUT_GET, 'loyalty', FILTER_SANITIZE_STRING):'';
$mytable = $user . "collection";
$adv = isset($_GET['adv']) ? filter_input(INPUT_GET, 'adv', FILTER_SANITIZE_STRING):'';
$scope = isset($_GET['scope']) ? filter_input(INPUT_GET, 'scope', FILTER_SANITIZE_STRING):'';
$legal = isset($_GET['legal']) ? filter_input(INPUT_GET, 'legal', FILTER_SANITIZE_STRING):'';
$foilonly = isset($_GET['foilonly']) ? filter_input(INPUT_GET, 'foilonly', FILTER_SANITIZE_STRING):'';

// Does the user have a collection table?
$tablecheck = "SELECT * FROM $mytable";
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if user has a collection table...",$logfile);
if($db->query($tablecheck) === FALSE):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": No existing collection table...",$logfile);
    $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": ...copying collection template...: $query2",$logfile);
    if($db->query($query2) === TRUE):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy successful",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy failed",$logfile);
    endif;
endif;
    
// More general query building:
$selectAll = "SELECT 
                cards_scry.id as cs_id,
                price,
                price_foil,
                setcode,
                `$mytable`.normal,
                `$mytable`.foil,
                number,
                number_import,
                name,
                promo,
                cards_scry.foil as cs_foil,
                cards_scry.nonfoil as cs_normal,
                release_date,
                rarity,
                set_name,
                type,
                ability,
                manacost,
                layout,
                p1_component,
                p2_component,
                p3_component,
                p1_name,
                p2_name,
                p3_name
                FROM cards_scry
                LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id
                WHERE ";
$sorting = "LIMIT $start_from, $perpage";
$maxresults = 1000;
require('includes/criteria.php'); //Builds $criteria and assesses validity
// If search is Mycollection / Sort By Price: 
// Update pricing in case any new cards have been added to collection
if (($sortBy == 'price') AND ( $scope == 'mycollection')):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"My Collection / Price query called, updating collection pricing",$logfile);
    $findnormalqry = "SELECT * FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id WHERE `$mytable`.normal / `$mytable`.normal IS TRUE AND `$mytable`.foil / `$mytable`.foil IS NOT TRUE";
    if($findnormal = $db->query($findnormalqry)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
    else:
        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
    endif;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Number of normal results = ".$findnormal->num_rows,$logfile);
    while ($row = $findnormal->fetch_array(MYSQLI_BOTH)):
        if($row['price'] == ''):
            $normalprice = '0.00';
        else:
            $normalprice = $db->real_escape_string($row['price']);
        endif;
        $cardid = $db->real_escape_string($row['id']);
        $updatemaxqry = "INSERT INTO `$mytable` (topvalue,id)
                VALUES ('$normalprice','$cardid')
                ON DUPLICATE KEY UPDATE `topvalue`='$normalprice'";
        if($updatemax = $db->query($updatemaxqry)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded for normal $cardid",$logfile);
        else:    
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        endif;
    endwhile;
    $findfoilqry   = "SELECT * FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id WHERE `$mytable`.foil / `$mytable`.foil IS TRUE";
    if($findfoil = $db->query($findfoilqry)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
    else:
        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
    endif;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Number of foil results = ".$findfoil->num_rows,$logfile);
    while ($rowfoil = $findfoil->fetch_array(MYSQLI_BOTH)):
        if($rowfoil['price_foil'] == ''):
            $foilprice = '0.00';
        else:
            $foilprice = $db->real_escape_string($rowfoil['price_foil']);
        endif;
        $cardid = $db->real_escape_string($rowfoil['id']);
        $updatemaxqry = "INSERT INTO `$mytable` (topvalue,id)
                VALUES ('$foilprice','$cardid')
                ON DUPLICATE KEY UPDATE topvalue='$foilprice'";
        if($updatemax = $db->query($updatemaxqry)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded for foil $cardid",$logfile);
        else:    
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        endif;
    endwhile;
    if($findnormal->num_rows == 0 AND $findfoil->num_rows == 0):
        $validsearch = "zero";
    endif;
endif;
//Set variable to ignore maxresults if this is a collection search
if ( $scope == 'mycollection'):
    $collectionsearch = true;
else:
    $collectionsearch = false;
endif;    
// Run the query
if ($validsearch === "true"):
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User $useremail called query $query from {$_SERVER['REMOTE_ADDR']}",$logfile);
    ?>
    <?php
    if($result = $db->query($query)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
        $queryQty = $db->query($selectAll . $criteria);
        $qtyresults = $queryQty->num_rows;
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Query has $qtyresults results",$logfile);
        if ($qtyresults > $maxresults AND $collectionsearch == false):
            $validsearch = "toomany"; //variable set for header.php to display warning
        endif;
    else:
        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
    endif;
endif;
# query for page navigation
if (isset($qtyresults)):
    if ($qtyresults > ($page * $perpage)):
        $next = $page + 1;
    endif;
    // Work out number of results, and pages
    if (($qtyresults - $start_from) <= $perpage) :
        $lastresult = $qtyresults;
    else :
        $lastresult = $page * $perpage;
    endif;
    $totalpages = $qtyresults / $perpage;
endif;
// Get the current GET string, less the layout and page keys if in there
// also run input through htmlspecialchars (via function)
$getstringbulk = getStringParameters($_GET, 'layout', 'page');

// Page layout starts here
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="initial-scale=1">
        <title> MtG collection </title>
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
        <?php include('includes/googlefonts.php'); ?>
        <script src="/js/jquery.js"></script>
        <script type="text/javascript">
            // Selecting Notes search deselects other scope options, and vice versa. Need to functionalise this.
            jQuery(function ($) {
                $('#yesnotes').click(function (event) {
                    if (this.checked) { // check select status of "yesnotes"
                        $('.notnotes').each(function () { //loop through and deselect each "notnotes" checkbox
                            this.checked = false;
                        });
                    }
                });
                $('.notnotes').click(function (event) {
                    if (this.checked) { // check select status when clicking on a "notnotes"
                        $('#yesnotes').each(function () { // deselect "yesnotes"
                            this.checked = false;
                        });
                    }
                });
                $('#abilityall').click(function (event) {
                    if (this.checked) { // check select status of "abilityall"
                        $('#abilityexact').each(function () { //deselect "abilityexact"
                            this.checked = false;
                        });
                    }
                });
                $('#abilityexact').click(function (event) {
                    if (this.checked) {  // check select status of "abilityexact"
                        $('#abilityall').each(function () { //deselect "abilityall"
                            this.checked = false;
                        });
                    }
                });
            });
        </script>
        
        <?php
        if ((isset($qtyresults)) AND ( $qtyresults != 0)): //Only load these scripts if this is a results call ?>
            <script src="https://unpkg.com/@webcreate/infinite-ajax-scroll@3/dist/infinite-ajax-scroll.min.js"></script>
            <script type="text/javascript">
                $(document).ready(function () { // Infinite Ajax Scroll configuration
                    $(".top").hide();
                    let ias = new InfiniteAjaxScroll('.wrap', {
                        item: '.item', // single items
                        next: '.next',
                        pagination: '.pagination', // page navigation
                        negativeMargin: 250,
                        spinner: {
                            element: '.spinner',
                            delay: 600,
                            show: function(element) {
                                element.style.opacity = '1'; // default behaviour
                            },
                            hide: function(element) {
                                element.style.opacity = '0'; // default behaviour
                            }
                        }
                    });
                    ias.on('page', (event) => {
                        $(".top").show(200);
                    });

                    ias.on('last', function() {
                        let el = document.querySelector('.ias-no-more');
                        el.style.opacity = '1';
                    });
                    // update title and url when scrolling through pages
                    ias.on('page', (e) => {
                        document.title = e.title;
                        let state = history.state;
                        history.replaceState(state, e.title, e.url);
                    });
                });
            </script>
            <script type="text/javascript">
                function isInteger(x) {
                    return x % 1 === 0;
                };
            </script>
            <script type="text/javascript">
                function ajaxUpdate(cardid,cellid,qty,flash,type) {
                    var activeCell = document.getElementById(cellid);
                    var activeFlash = document.getElementById(flash);
                    var poststring = type + '=' + (activeCell.value) + '&cardid=' + cardid;
                    if ((activeCell.value) == '') {
                        alert("Enter a number");
                        activeCell.focus();
                    } else if (!isInteger(activeCell.value)) {
                        alert("Enter an integer");
                        activeCell.focus();
                    } else {
                        $.ajax({
                            type: "GET",
                            url: "gridupdate.php",
                            data: poststring,
                            cache: true,
                            success: function (data) {
                                $(activeFlash).hide(300);
                                $(activeFlash).show(300);
                            }
                        });
                    }
                    return false;
                };
            </script>
  <?php endif;?>
    </head>
    <body>
<?php include_once("includes/analyticstracking.php") ?>
        <?php $getString = getStringParameters($_GET, 'page'); ?>
        <div class="top"> <?php echo "<a id='prevlink' href='index.php{$getString}&amp;page=1'>&nbsp;</a>"; ?>
        </div>
        <?php
        require('includes/overlays.php'); //menus
        require('includes/header.php');  //build header
        require('includes/menu.php'); //mobile menu

        if ((isset($qtyresults)) AND ( $qtyresults != 0)) : //Display Bulk / List / Grid menus and results header row
            if ($layout == 'bulk') :
                require('includes/bulkmenus.php');
            elseif ($layout == 'list') :
                require('includes/listmenus.php');
            elseif ($layout == 'grid') :
                require('includes/gridmenus.php');
            endif;
        endif;
        ?>
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons"
        rel="stylesheet">  
        <div id='page'>
            <span id="printtitle" class="headername">
                <img src="images/white_m.png">MtG collection
            </span>
            <?php
            if ((isset($qtyresults)) AND ( $qtyresults != 0)):
                if ($layout == 'bulk') : ?>
                    <div id="resultsgrid" class='wrap'>
                        <?php
                        while ($row = $result->fetch_array(MYSQLI_BOTH)): //$row now contains all card info
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']}",$logfile);
                            $setcode = strtolower($row['setcode']);
                            $scryid = $row['cs_id'];
                            $card_normal = $row['cs_normal'];
                            $card_foil = $row['cs_foil'];
                            $promo = $row['promo'];
                            if($card_normal != 1 AND $card_foil == 1):
                                $cardtypes = 'foilonly';
                            elseif($card_normal == 1 AND $card_foil != 1):
                                $cardtypes = 'normalonly';
                            else:
                                $cardtypes = 'normalandfoil';
                            endif;
                            $uppercasesetcode = strtoupper($setcode);
                            if(($row['p1_component'] === 'meld_result' AND $row['p1_name'] === $row['name']) OR ($row['p2_component'] === 'meld_result' AND $row['p2_name'] === $row['name']) OR ($row['p3_component'] === 'meld_result' AND $row['p3_name'] === $row['name'])):
                                $meld = 'meld_result';
                            elseif($row['p1_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part'):
                                $meld = 'meld_part';
                            else:
                                $meld = '';
                            endif;
                            // If the current record has null fields set the variables to 0 so updates
                            // from the Grid work.
                            if (empty($row['normal'])):
                                $myqty = 0;
                            else:
                                $myqty = $row['normal'];
                            endif;
                            if (empty($row['foil'])):
                                $myfoil = 0;
                            else:
                                $myfoil = $row['foil'];
                            endif;
                            ?>
                            <div class='gridbox gridboxbulk item'><?php
                                echo "&nbsp;&nbsp;<a class='gridlinkbulk' target='carddetail' href='/carddetail.php?id={$row['cs_id']}' tabindex='-1'>{$uppercasesetcode} {$row['number']} {$row['name']}</a>";
                                $cellid = "cell".$scryid;
                                $cellidqty = $cellid.'myqty';
                                $cellidfoil = $cellid.'myfoil';
                                $cellidflash = $cellid.'flash';
                                $cellidfoilflash = $cellid.'flashfoil';
                                echo "<div class='confirm-l-bulk' id='{$cellid}flash'><span class='material-icons md-24 green'>check</span></div>";
                                echo "<div class='confirm-r-bulk' id='{$cellid}flashfoil'><span class='material-icons md-24 green'>check</span></div>";
                                ?>
                                <table class='gridupdatetable'>
                                    <tr>
                                        <td class='gridsubmit gridsubmit-l' id="<?php echo $cellid . "td"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "Meld card";
                                            elseif ($cardtypes === 'foilonly'):
                                                $poststring = 'newfoil';
                                                echo "Quantity: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myfoil\",\"$cellidflash\",\"$poststring\");'>";
                                            elseif ($cardtypes === 'normalonly'):
                                                $poststring = 'newqty';
                                                echo "Quantity: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            elseif ($cardtypes === 'normalandfoil'):
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            else:
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            endif;
                                            echo "<input class='card' type='hidden' name='card' value='$scryid'>"; ?>
                                        </td>
                                        <td class='confirm-r'>&nbsp;</td>
                                        <td class='gridsubmit gridsubmit-r' id="<?php echo $cellid . "tdfoil"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'foilonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalandfoil'):
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='textinput' id='$cellidfoil' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellidfoil\",\"$myfoil\",\"$cellidfoilflash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>    
                  <?php endwhile; ?>
                        <div class="ias-no-more">NO MORE RESULTS</div>
                        <div class="spinner"><img src='/images/ajax-loader.gif' alt="LOADING"></div>
                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="pagination"> <?php echo "<a href='index.php{$getString}&amp;page=$next' class='next'>Next</a>"; ?>
                            </div>
                        <?php endif ?>
                        <table class='bottompad'>
                            <tr>
                                <td>
                                    &nbsp;
                                </td>
                            </tr>
                        </table>    
                    </div>
          <?php elseif ($layout == 'list'):?>
                    <div id='results' class='wrap'>
                        <?php
                        while ($row = $result->fetch_array(MYSQLI_BOTH)) : 
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']}",$logfile);
                            $scryid = $row['cs_id']; ?>
                            <div class='item' style="cursor: pointer;" onclick="location.href='carddetail.php?id=<?php echo $scryid;?>';">
                                <table> <?php
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: $scryid",$logfile);
                                    $setcode = strtolower($row['setcode']);
                                    $uppercasesetcode = strtoupper($setcode);
                                    $scryid = $row['cs_id'];
                                    if(($row['p1_component'] === 'meld_result' AND $row['p1_name'] === $row['name']) OR ($row['p2_component'] === 'meld_result' AND $row['p2_name'] === $row['name']) OR ($row['p3_component'] === 'meld_result' AND $row['p3_name'] === $row['name'])):
                                        $meld = 'meld_result';
                                    elseif($row['p1_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part'):
                                        $meld = 'meld_part';
                                    else:
                                        $meld = '';
                                    endif;
                                    ?>
                                    <tr class='resultsrow'>
                                        <td class="valuename"> <?php echo "{$row['name']}"; ?> </td>    
                                            <?php
                                            $manac = symbolreplace($row['manacost']);
                                            ?>
                                        <td class="valuerarity"> <?php echo ucfirst($row['rarity']); ?> </td>
                                        <td class="valueset"> <?php echo $row['set_name']; ?> </td>
                                        <td class="valuetype"> <?php echo $row['type']; ?> </td>
                                        <td class="valuenumber"> <?php echo $row['number']; ?> </td>
                                        <td class="valuemana"> <?php echo $manac; ?> </td>
                                        <td class="valuecollection">
                                            <?php
                                            echo $row['normal'] + $row['foil'];
                                            ?>
                                        </td>
                                        <td class="valueabilities"> 
                                            <?php
                                            $ability = symbolreplace($row['ability']);
                                            echo $ability;
                                            ?> 
                                        </td>
                                    </tr>
                                </table>
                            </div>
                  <?php endwhile; ?>
                        <div class="ias-no-more">NO MORE RESULTS</div>
                        <div class="spinner"><img src='/images/ajax-loader.gif' alt="LOADING"></div>
                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="pagination"> <?php echo "<a href='index.php{$getString}&amp;page=$next' class='next'>Next</a>"; ?>
                            </div>
                        <?php endif ?>
                        <table class='bottompad'>
                            <tr>
                                <td>
                                    &nbsp;
                                </td>
                            </tr>
                        </table>    
                    </div>
          <?php elseif ($layout == 'grid') :?>
                    <script type="text/javascript">
                        function swapImage(img_id,card_id,imageurl,imagebackurl){
                            var ImageId = document.getElementById(img_id);
                            var FrontImg = card_id + ".jpg";
                            var BackImg = card_id + "_b.jpg";
                            if (ImageId.src.match(FrontImg))
                            { 
                                document.getElementById(img_id).src = imagebackurl; 
                            } else if (ImageId.src.match(BackImg))
                            {
                                document.getElementById(img_id).src = imageurl; 
                            }
                        };
                    </script>
                    <script type="text/javascript">
                        function rotateImg(img_id) {
                            if ( document.getElementById(img_id).style.transform == 'none' ){
                                document.getElementById(img_id).style.transform = "rotate(180deg)";
                            } 
                            else if ( document.getElementById(img_id).style.transform == '' ){
                                document.getElementById(img_id).style.transform = "rotate(180deg)";
                            } else {
                                document.getElementById(img_id).style.transform = "none";
                            }
                        };
                    </script>
                    <div id="resultsgrid" class='wrap'>
                        <?php
                        $x = 1;
                        while ($row = $result->fetch_array(MYSQLI_BOTH)): 
                            $flipbutton = $row['cs_id']."flip";
                            $img_id = $row['cs_id']."img";
                            $setcode = strtolower($row['setcode']);
                            $scryid = $row['cs_id'];
                            $card_normal = $row['cs_normal'];
                            $card_foil = $row['cs_foil'];
                            $promo = $row['promo'];
                            if($card_normal != 1 AND $card_foil == 1):
                                $cardtypes = 'foilonly';
                            elseif($card_normal == 1 AND $card_foil != 1):
                                $cardtypes = 'normalonly';
                            else:
                                $cardtypes = 'normalandfoil';
                            endif;
                            $uppercasesetcode = strtoupper($setcode);
                            if(($row['p1_component'] === 'meld_result' AND $row['p1_name'] === $row['name']) OR ($row['p2_component'] === 'meld_result' AND $row['p2_name'] === $row['name']) OR ($row['p3_component'] === 'meld_result' AND $row['p3_name'] === $row['name'])):
                                $meld = 'meld_result';
                            elseif($row['p1_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part'):
                                $meld = 'meld_part';
                            else:
                                $meld = '';
                            endif;
                            $imagefunction = getImageNew($setcode,$row['cs_id'],$ImgLocation,$row['layout']);
                            if($imagefunction['front'] == 'error'):
                                $imageurl = '/cardimg/back.jpg';
                            else:
                                $imageurl = $imagefunction['front'];
                            endif;
                            
                            if(!is_null($imagefunction['back'])):
                                if($imagefunction['back'] === 'error' OR $imagefunction['back'] === 'error'):
                                    $imagebackurl = '/cardimg/back.jpg';
                                else:
                                    $imagebackurl = $imagefunction['back'];
                                endif;
                            endif;
                            // If the current record has null fields set the variables to 0 so updates
                            // from the Grid work.
                            // if (!isset($_POST["update"])) :    
                            if (empty($row['normal'])):
                                $myqty = 0;
                            else:
                                $myqty = $row['normal'];
                            endif;
                            if (empty($row['foil'])):
                                $myfoil = 0;
                            else:
                                $myfoil = $row['foil'];
                            endif;
                            ?>
                            <div class='gridbox item'>
                                <?php
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." $imageurl",$logfile);
                                $reversible_layouts = ['transform','modal_dfc','reversible_card','double_faced_token'];
                                if(in_array($row['layout'],$reversible_layouts)):
                                    echo "<div style='cursor: pointer;' class='flipbutton' onclick=swapImage(\"{$img_id}\",\"{$row['cs_id']}\",\"{$imageurl}\",\"{$imagebackurl}\")><span class='material-icons md-24'>refresh</span></div>";
                                elseif($row['layout'] === 'flip'):
                                    echo "<div style='cursor: pointer;' class='flipbutton' onclick=rotateImg(\"{$img_id}\")><span class='material-icons md-24'>refresh</span></div>";
                                endif;
                                echo "<a class='gridlink' target='carddetail' href='/carddetail.php?id=$scryid'><img id='$img_id' title='$uppercasesetcode ({$row['set_name']}) no. {$row['number_import']}' class='cardimg' alt='$scryid' src='$imageurl'></a>";
                                $cellid = "cell".$scryid;
                                $cellidqty = $cellid.'myqty';
                                $cellidfoil = $cellid.'myfoil';
                                $cellidflash = $cellid.'flash';
                                $cellidfoilflash = $cellid.'flashfoil';
                                echo "<div class='confirm-l-grid' id='$cellidflash'><span class='material-icons md-24 green'>check</span></div>";
                                echo "<div class='confirm-r-grid' id='$cellidfoilflash'><span class='material-icons md-24 green'>check</span></div>";
                                ?>
                                <table class='gridupdatetable'>
                                    <tr>
                                        <td class='gridsubmit gridsubmit-l' id="<?php echo "$cellid.td"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "Meld card";
                                            elseif ($cardtypes === 'foilonly'):
                                                $poststring = 'newfoil';
                                                echo "Quantity: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myfoil\",\"$cellidflash\",\"$poststring\");'>";
                                            elseif ($cardtypes === 'normalonly'):
                                                $poststring = 'newqty';
                                                echo "Quantity: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            elseif ($cardtypes === 'normalandfoil'):
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            else:
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='textinput' id='$cellidqty' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellidqty\",\"$myqty\",\"$cellidflash\",\"$poststring\");'>";
                                            endif;
                                            echo "<input class='card' type='hidden' name='card' value='$scryid'>"; ?>
                                        </td>
                                        <td class='confirm-l'>&nbsp;</td>
                                        <td class='confirm-r'>&nbsp;</td>
                                        <td class='gridsubmit gridsubmit-r' id="<?php echo "$cellid.tdfoil"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'foilonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalandfoil'):
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='textinput' id='$cellidfoil' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellidfoil\",\"$myfoil\",\"$cellidfoilflash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>    
                  <?php endwhile; ?>
                        <div class="ias-no-more">NO MORE RESULTS</div>
                        <div class="spinner"><img src='/images/ajax-loader.gif' alt="LOADING"></div>
                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="pagination"> <?php echo "<a href='index.php{$getString}&amp;page=$next' class='next'>Next</a>"; ?>
                            </div>
                  <?php endif ?>
                        <table class='bottompad'>
                            <tr>
                                <td>
                                    &nbsp;
                                </td>
                            </tr>
                        </table>    
                    </div>
                    <?php
                endif;
            else :
                require('includes/search.php');
            endif;
            ?>
        </div>
<?php require('includes/footer.php'); ?>
    </body>
</html>
