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

// More general query building:
$selectAll = "SELECT 
                cards_scry.id as cs_id,
                price,
                price_foil,
                setcode,
                `$mytable`.normal,
                `$mytable`.foil,
                number,
                name,
                promostatus,
                release_date,
                rarity,
                set_name,
                type,
                ability,
                manacost,
                layout
                FROM cards_scry
                LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id
                LEFT JOIN setsPromo ON cards_scry.setcode = setsPromo.promosetcode
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
        <script type="text/javascript" src="/js/jquery-ias.min.js"></script>
        <script type="text/javascript">
            jQuery(function ($) {
                $('tbody tr[data-href]').addClass('clickable').click(function () {
                    window.location = $(this).attr('data-href');
                });
            });
        </script>
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
        <script type="text/javascript">
            jQuery(window).load(function () {
                jQuery("img").each(function () {
                    var image = jQuery(this);
                    if (image.context.naturalWidth == 0 ||
                            image.readyState == 'uninitialized') {
                        jQuery(image).unbind("error").attr(
                                "src", "/cardimg/back.jpg"
                                );
                    }
                });
            });
        </script>
        <script type="text/javascript">
            $(document).ready(function () {
                // Infinite Ajax Scroll configuration
                var ias = jQuery.ias({
                    container: '.wrap', // main container where data goes to append
                    item: '.item', // single items
                    pagination: '.nav', // page navigation
                    next: '.nav a', // next page selector
                    negativeMargin: 250
                });
                ias.extension(new IASNoneLeftExtension({
                    text: "NO MORE RESULTS"
                }));
                ias.extension(new IASSpinnerExtension({
                    src: '/images/ajax-loader.gif'
                }));
                ias.extension(new IASPagingExtension());
                $(document).ready(function () {
                    var phppagevalue = <?php
        if (empty($page)):$page = 1;
        endif;
        echo $page;
        ?>;
                    if (phppagevalue === 1)
                    {
                        $(".previous").hide();
                    } else
                    {
                        $(".previous").show();
                    }
                });
                jQuery.ias().on('pageChange', function (pageNum, scrollOffset, url) {
                    var queryParameters = {}, queryString = location.search.substring(1), re = /([^&=]+)=([^&]*)/g, m;
                    while (m = re.exec(queryString)) {
                        queryParameters[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
                    }
                    if (queryParameters['page'] > 1)
                    {
                        $(".previous").show();
                    } else if (pageNum > 1) {
                        $(".previous").show();
                    } else {
                        $(".previous").hide(200);
                    }
                });
                jQuery.ias().on('rendered', function () {
                    jQuery(function ($) {
                        $('tbody tr[data-href]').addClass('clickable').click(function () {
                            window.location = $(this).attr('data-href');
                        });
                    });
                })
            });
        </script>

    </head>
    <body>
<?php include_once("includes/analyticstracking.php") ?>
        <?php $getString = getStringParameters($_GET, 'page'); ?>
        <div class="previous"> <?php echo "<a id='prevlink' href='index.php{$getString}&amp;page=1'>&nbsp;</a>"; ?>
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

        <div id='page'>
            <span id="printtitle" class="headername">
                <img src="images/white_m.png">MtG collection
            </span>
            <?php
            if ((isset($qtyresults)) AND ( $qtyresults != 0)):
                if ($layout == 'bulk') :
                    ?>
                    <div id="resultsgrid" class='wrap'>
                        <?php
                        while ($row = $result->fetch_array(MYSQLI_BOTH)): //$row now contains all card info
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']}",$logfile);
                            $setcode = strtolower($row['setcode']);
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
                                $uppercasesetcode = strtoupper($setcode);
                                echo "&nbsp;&nbsp;<a class='gridlinkbulk' target='carddetail' href='/carddetail.php?id={$row['cs_id']}' tabindex='-1'>{$uppercasesetcode} {$row['number']} {$row['name']}</a>";
                                $cellid = "cell" . $row['cs_id'];
                                ?>
                                <table class='gridupdatetable'>
                                    <tr>
                                            <td class='gridsubmit gridsubmit-l' id="<?php echo $cellid . "td"; ?>">
                                                <?php
                                                if (!$row['promostatus']):
                                                    echo " Normal: <input class='textinput' id='" . $cellid . "myqty' type='number' step='1' min='0' name='myqty' value=" . $myqty . ">";
                                                else:
                                                    echo " Quantity: <input class='textinput' id='" . $cellid . "myqty' type='number' step='1' min='0' name='myqty' value=" . $myqty . ">";
                                                endif;
                                                echo "<input class='card' type='hidden' name='card' value=" . $row['cs_id'] . ">";
                                                ?>
                                                <script type="text/javascript">
                                                    function isInteger(x) {
                                                        return x % 1 === 0;
                                                    }
                                                    $(function () {
                                                        $("#<?php echo $cellid . "myqty"; ?>").change(function () {
                                                            var ths = this;
                                                            var card = $(ths).siblings(".card").val();
                                                            var myqty = $(ths).val();
                                                            var poststring = 'newqty=' + myqty + '&cardid=' + card;
                                                            if (myqty == '')
                                                            {
                                                                alert("Enter a number");
                                                                $("#content").focus();
                                                            } else if (!isInteger(myqty))
                                                            {
                                                                alert("Enter an integer");
                                                                $("#content").focus();
                                                            } else
                                                            {
                                                                $.ajax({
                                                                    type: "GET",
                                                                    url: "gridupdate.php",
                                                                    data: poststring,
                                                                    cache: true,
                                                                    success: function (data) {
                                                                        jQuery('#<?php echo $cellid . "flash"; ?>').fadeOut(200).fadeIn(200, function () {
                                                                            jQuery(this).html(data);
                                                                        });
                                                                    }
                                                                });
                                                            }
                                                            return false;
                                                        });
                                                    });
                                                </script>
                                            </td>
                                            <?php
                                            echo "<td class='confirm-l' id='" . $cellid . "flash'></td>";
                                        echo "<td class='confirm-r' id='" . $cellid . "flashfoil'></td>";
                                            ?>
                                            <td class='gridsubmit gridsubmit-r' id="<?php echo $cellid . "tdfoil"; ?>">
                                                <?php
                                                if (!$row['promostatus']):
                                                    echo " Foil: <input class='textinput' id='" . $cellid . "myfoil' type='number' step='1' min='0' name='myfoil' value=" . $myfoil . ">";
                                                    echo "<input class='card' type='hidden' name='card' value=" . $row['cs_id'] . ">";
                                                endif;
                                                ?>
                                                <script type="text/javascript">
                                                    function isInteger(x) {
                                                        return x % 1 === 0;
                                                    }

                                                    $(function () {
                                                        $("#<?php echo $cellid . "myfoil"; ?>").change(function () {
                                                            var ths = this;
                                                            var card = $(ths).siblings(".card").val();
                                                            var myfoil = $(ths).val();
                                                            var poststring = 'newfoil=' + myfoil + '&cardid=' + card;
                                                            if (myfoil == '')
                                                            {
                                                                alert("Enter a number");
                                                                $("#content").focus();
                                                            } else if (!isInteger(myfoil))
                                                            {
                                                                alert("Enter an integer");
                                                                $("#content").focus();
                                                            } else
                                                            {
                                                                $.ajax({
                                                                    type: "GET",
                                                                    url: "gridupdate.php",
                                                                    data: poststring,
                                                                    cache: true,
                                                                    success: function (data) {
                                                                        jQuery('#<?php echo $cellid . "flashfoil"; ?>').fadeOut(200).fadeIn(200, function () {
                                                                            jQuery(this).html(data);
                                                                        });
                                                                    }
                                                                });
                                                            }
                                                            return false;
                                                        });
                                                    });
                                                </script>
                                            </td>
                                            <?php
                                       
                                        ?>
                                    </tr>
                                </table>
                            </div>    
                        <?php endwhile; ?>

                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="nav"> <?php echo "<a href='index.php" . $getString . "&amp;page=$next'>Next</a>"; ?>
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
                        <?php elseif ($layout == 'list'):
                            ?>
                    <div id='results' class='wrap'>
                        <table>
                            <?php
                            while ($row = $result->fetch_array(MYSQLI_BOTH)) :
                                $setcode = strtolower($row['setcode']);
                                ?>
                                <tr class='resultsrow item' <?php echo "data-href='carddetail.php?id={$row['cs_id']}'"; ?>>
                                    <td class="valuename"> <?php echo "{$row['name']}"; ?> </td>    
                                        <?php
                                        $manac = symbolreplace($row['manacost']);
                                        ?>
                                    <td class="valuerarity"> <?php echo $row['rarity']; ?> </td>
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
                        <?php endwhile; ?>
                        </table>
                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="nav"> <?php echo "<a href='index.php" . $getString . "&amp;page=$next'>Next</a>"; ?>
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
          <?php elseif ($layout == 'grid') :
                        ?>
                    <div id="resultsgrid" class='wrap'>
                        <?php
                        $x = 1;
                        while ($row = $result->fetch_array(MYSQLI_BOTH)):
                            $setcode = strtolower($row['setcode']);
                            $imagefunction = getImageNew($setcode,$row['cs_id'],$ImgLocation,$row['layout']);
                            $imageurl = $imagefunction['front'];
                            if(!is_null($imagefunction['back'])):
                                $imagebackurl = $imagefunction['back'];
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
                            <div class='gridbox item'><?php
                                        echo "<a class='gridlink' target='carddetail' href='/carddetail.php?id={$row['cs_id']}'><img class='cardimg' alt='{$row['cs_id']}' src=$imageurl></a>";
                                        $cellid = "cell" . $row['cs_id'];
                                        ?>
                                <table class='gridupdatetable'>
                                    <tr>
                                        <td class='gridsubmit gridsubmit-l' id="<?php echo "$cellid.td"; ?>">
                                                <?php
                                                if (!$row['promostatus']):
                                                    echo " Normal: <input class='textinput' id='" . $cellid . "myqty' type='number' step='1' min='0' name='myqty' value=" . $myqty . ">";
                                                else:
                                                    echo " Quantity: <input class='textinput' id='" . $cellid . "myqty' type='number' step='1' min='0' name='myqty' value=" . $myqty . ">";
                                                endif;
                                                echo "<input class='card' type='hidden' name='card' value=" . $row['cs_id'] . ">";
                                                ?>
                                                <script type="text/javascript">
                                                    function isInteger(x) {
                                                        return x % 1 === 0;
                                                    }

                                                    $(function () {
                                                        $("#<?php echo $cellid . "myqty"; ?>").change(function () {
                                                            var ths = this;
                                                            var card = $(ths).siblings(".card").val();
                                                            var myqty = $(ths).val();
                                                            var poststring = 'newqty=' + myqty + '&cardid=' + card;
                                                            if (myqty == '')
                                                            {
                                                                alert("Enter a number");
                                                                $("#content").focus();
                                                            } else if (!isInteger(myqty))
                                                            {
                                                                alert("Enter an integer");
                                                                $("#content").focus();
                                                            } else
                                                            {
                                                                $.ajax({
                                                                    type: "GET",
                                                                    url: "gridupdate.php",
                                                                    data: poststring,
                                                                    cache: true,
                                                                    success: function (data) {
                                                                        jQuery('#<?php echo $cellid . "flash"; ?>').fadeOut(200).fadeIn(200, function () {
                                                                            jQuery(this).html(data);
                                                                        });
                                                                    }
                                                                });
                                                            }
                                                            return false;
                                                        });
                                                    });
                                                </script>
                                            </td>
                                            <?php
                                            echo "<td class='confirm-l' id='" . $cellid . "flash'></td>";
                                            echo "<td class='confirm-r' id='" . $cellid . "flashfoil'></td>";
                                            ?>
                                            <td class='gridsubmit gridsubmit-r' id="<?php echo "$cellid.tdfoil"; ?>">
                                                <?php
                                                if (!$row['promostatus']):
                                                    echo " Foil: <input class='textinput' id='" . $cellid . "myfoil' type='number' step='1' min='0' name='myfoil' value=" . $myfoil . ">";
                                                    echo "<input class='card' type='hidden' name='card' value=" . $row['cs_id'] . ">";
                                                endif;
                                                ?>
                                                <script type="text/javascript">
                                                    function isInteger(x) {
                                                        return x % 1 === 0;
                                                    }

                                                    $(function () {
                                                        $("#<?php echo $cellid . "myfoil"; ?>").change(function () {
                                                            var ths = this;
                                                            var card = $(ths).siblings(".card").val();
                                                            var myfoil = $(ths).val();
                                                            var poststring = 'newfoil=' + myfoil + '&cardid=' + card;
                                                            if (myfoil == '')
                                                            {
                                                                alert("Enter a number");
                                                                $("#content").focus();
                                                            } else if (!isInteger(myfoil))
                                                            {
                                                                alert("Enter an integer");
                                                                $("#content").focus();
                                                            } else
                                                            {
                                                                $.ajax({
                                                                    type: "GET",
                                                                    url: "gridupdate.php",
                                                                    data: poststring,
                                                                    cache: true,
                                                                    success: function (data) {
                                                                        jQuery('#<?php echo $cellid . "flashfoil"; ?>').fadeOut(200).fadeIn(200, function () {
                                                                            jQuery(this).html(data);
                                                                        });
                                                                    }
                                                                });
                                                            }
                                                            return false;
                                                        });
                                                    });
                                                </script>
                                            </td>
                                            <?php
                                        ?>
                                    </tr>
                                </table>

                            </div>    
                        <?php endwhile; ?>

                        <!--page navigation-->
                        <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="nav"> <?php echo "<a href='index.php" . $getString . "&amp;page=$next'>Next</a>"; ?>
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
