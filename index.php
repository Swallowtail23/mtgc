<?php
/* Version:     10.0
    Date:       09/12/23
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
 *  6.0
 *              Layout changes for Arena cards
 *  7.0
 *              Add flip capability for battle cards
 *  8.0
 *              Changes to handle etched cards
 *
 *  9.0         02/12/23
 *              Add javascript to add/remove b/w based on cview mode
 *
 * 10.0         09/12/23
 *              Move main search to parameterised queries  
*/

//Call script initiation mechs
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                               //Check if user is disabled or needs to change password
$msg = new Message;

// Default numbers per page and max
$listperpage = 30;
$gridperpage = 30;
$bulkperpage = 1000;
$maxresults = 2500;
$time = time();

// Is admin running the page
$msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin is $admin",$logfile);

// Define layout and results per page for each layout type
if (isset($_GET['layout'])):
    $valid_layout = array("grid","list","bulk");
    $layout = $_GET['layout'];
    if (!in_array($layout,$valid_layout)):
        $layout == 'grid';
    endif;
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
    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT);
else :
    $page = 1;
endif;
$perpage = (int)$perpage;
$start_from = ($page - 1) * $perpage;
$start_from = (int)$start_from;
if (isset($_GET['name']) AND $_GET['name'] !== ""):
    $nameget = htmlspecialchars($_GET["name"],ENT_NOQUOTES);
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name in GET is $nameget",$logfile);
    $nametrim = trim($nameget, " \t\n\r\0\x0B");
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name after trimming is $nametrim",$logfile);
    $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
    $name = preg_replace($regex, ' ', $nametrim);
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Name after URL removal is $name",$logfile);
    // Remove any embedded setcodes in [] into a variable
    preg_match('/\[(.*?)\]/', $name, $matches);
    $setcodesearch = isset($matches[1]) ? $matches[1] : '';
    $name = trim(preg_replace('/\[[A-Za-z0-9]+\]/', '', $name));
else:
    $name = '';
endif;
$searchname = isset($_GET['searchname']) ? 'yes' : '';
$searchtype = isset($_GET['searchtype']) ? 'yes' : '';
$searchsetcode = isset($_GET['searchsetcode']) ? 'yes' : '';
$searchability = isset($_GET['searchability']) ? 'yes' : '';
$searchabilityexact = isset($_GET['searchabilityexact']) ? 'yes' : '';
$searchnotes = isset($_GET['searchnotes']) ? 'yes' : '';
$searchpromo = isset($_GET['searchpromo']) ? 'yes' : '';
$new = isset($_GET['searchnew']) ? 'yes' : '';
$white = isset($_GET['white']) ? 'yes' : '';
$blue = isset($_GET['blue']) ? 'yes' : '';
$black = isset($_GET['black']) ? 'yes' : '';
$red = isset($_GET['red']) ? 'yes' : '';
$green = isset($_GET['green']) ? 'yes' : '';
$artifact = isset($_GET['artifact']) ? 'yes' : '';
$colourless = isset($_GET['colourless']) ? 'yes' : '';
$land = isset($_GET['land']) ? 'yes' : '';
$battle = isset($_GET['battle']) ? 'yes' : '';
$valid_colourOp = array("and","or","");
$colourOp = isset($_GET['colourOp']) ? "{$_GET['colourOp']}" : '';
if (!in_array($colourOp,$valid_colourOp)):
    $colourOp == '';
endif;
$colourExcl = isset($_GET['colourExcl']) ? 'ONLY' : '';
$common = isset($_GET['common']) ? 'yes' : '';
$uncommon = isset($_GET['uncommon']) ? 'yes' : '';
$rare = isset($_GET['rare']) ? 'yes' : '';
$mythic = isset($_GET['mythic']) ? 'yes' : '';
$paper = isset($_GET['paper']) ? 'yes' : '';
$arena = isset($_GET['arena']) ? 'yes' : '';
$online = isset($_GET['online']) ? 'yes' : '';
$valid_gametypeOp = array("and","or","");
$gametypeOp = isset($_GET['gametypeOp']) ? "{$_GET['gametypeOp']}" : '';
if (!in_array($gametypeOp,$valid_gametypeOp)):
    $gametypeOp == '';
endif;
$gametypeExcl = isset($_GET['gametypeExcl']) ? 'ONLY' : '';
$online = isset($_GET['online']) ? 'yes' : '';
$creature = isset($_GET['creature']) ? 'yes' : '';
$instant = isset($_GET['instant']) ? 'yes' : '';
$sorcery = isset($_GET['sorcery']) ? 'yes' : '';
$enchantment = isset($_GET['enchantment']) ? 'yes' : '';
$planeswalker = isset($_GET['planeswalker']) ? 'yes' : '';
$tribal = isset($_GET['tribal']) ? 'yes' : '';
$valid_tribe = array("merfolk","goblin","treefolk","centaur","sliver","human","zombie","vampire");
$tribe = isset($_GET['tribe']) ? "{$_GET['tribe']}" : '';
if (!in_array($tribe,$valid_tribe)):
    $tribe == '';
endif;
$legendary = isset($_GET['legendary']) ? 'yes' : '';
$token = isset($_GET['token']) ? 'yes' : '';
# $rareOp = isset($_GET['rareOp']) ? filter_input(INPUT_GET, 'rareOp', FILTER_SANITIZE_STRING):'';
$exact = isset($_GET['exact']) ? 'yes' : '';
if ((isset($_GET['set'])) AND ( is_array($_GET['set']))):
    $selectedSets = filter_var_array($_GET['set'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
endif;
$valid_sortBy = array("name","price","cmc","cmcdown","set","setdown","setnumberdown","powerup","powerdown","toughup","toughdown");
$sortBy = isset($_GET['sortBy']) ? "{$_GET['sortBy']}" : '';
if (!in_array($sortBy,$valid_sortBy)):
    $sortBy == '';
endif;
$valid_operator = array("ltn","gtr","eq");
$poweroperator = isset($_GET['poweroperator']) ? "{$_GET['poweroperator']}" : '';
if (!in_array($poweroperator,$valid_operator)):
    $poweroperator == '';
endif;
$toughoperator = isset($_GET['toughoperator']) ? "{$_GET['toughoperator']}" : '';
if (!in_array($toughoperator,$valid_operator)):
    $toughoperator == '';
endif;
$loyaltyoperator = isset($_GET['loyaltyoperator']) ? "{$_GET['loyaltyoperator']}" : '';
if (!in_array($loyaltyoperator,$valid_operator)):
    $loyaltyoperator == '';
endif;
$cmcoperator = isset($_GET['cmcoperator']) ? "{$_GET['cmcoperator']}" : '';
if (!in_array($cmcoperator,$valid_operator)):
    $cmcoperator == '';
endif;
$cmcvalue = isset($_GET['cmcvalue']) ? filter_input(INPUT_GET, 'cmcvalue', FILTER_SANITIZE_NUMBER_INT):'';
$power = isset($_GET['power']) ? filter_input(INPUT_GET, 'power', FILTER_SANITIZE_NUMBER_INT):'';
$tough = isset($_GET['tough']) ? filter_input(INPUT_GET, 'tough', FILTER_SANITIZE_NUMBER_INT):'';
$loyalty = isset($_GET['loyalty']) ? filter_input(INPUT_GET, 'loyalty', FILTER_SANITIZE_NUMBER_INT):'';
$mytable = $user . "collection";
$adv = isset($_GET['adv']) ? 'yes' : '';
$scope = isset($_GET['scope']) ? "{$_GET['scope']}" : '';
$valid_scope = array("all","mycollection","notcollection");
if (!in_array($scope,$valid_scope)):
    $scope == '';
endif;
$valid_legal = array("std","pnr","mdn","vin","lgc","alc","his");
$legal = isset($_GET['legal']) ? "{$_GET['legal']}" : '';
if (!in_array($legal,$valid_legal)):
    $legal == '';
endif;
$foilonly = isset($_GET['foilonly']) ? 'yes' : '';

// Does the user have a collection table?
$tablecheck = "SELECT * FROM $mytable";
$msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if user has a collection table...",$logfile);
if($db->query($tablecheck) === FALSE):
    $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": No existing collection table...",$logfile);
    $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
    $msg->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": ...copying collection template...: $query2",$logfile);
    if($db->query($query2) === TRUE):
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy successful",$logfile);
    else:
        $msg->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy failed",$logfile);
    endif;
endif;
    
// More general query building:
$selectAll = "SELECT 
                cards_scry.id as cs_id,
                price,
                price_foil,
                price_etched,
                price_sort,
                setcode,
                `$mytable`.normal,
                `$mytable`.foil,
                `$mytable`.etched,
                number,
                number_import,
                cards_scry.name,
                game_types,
                finishes,
                cards_scry.foil as cs_foil,
                cards_scry.nonfoil as cs_normal,
                cards_scry.release_date,
                sets.release_date as set_date,
                rarity,
                cards_scry.set_name,
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
                LEFT JOIN `sets` ON cards_scry.setcode = sets.code
                WHERE ";
$sorting = "LIMIT $start_from, $perpage";
require('includes/criteria.php'); //Builds $criteria and assesses validity
// If search is Mycollection / Sort By Price: 
// Update pricing in case any new cards have been added to collection
if (($sortBy == 'price') AND ( $scope == 'mycollection')):
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"My Collection / Price query called, updating collection pricing",$logfile);
    $obj = new PriceManager($db,$logfile,$useremail);
    $obj->updateCollectionValues($mytable);
endif;
//Set variable to ignore maxresults if this is a collection search
if ( $scope == 'mycollection' OR $sortBy == 'price'): // Price search waives the limit
    $collectionsearch = true;
else:
    $collectionsearch = false;
endif;    
// Run the query
if ($validsearch === "true"):
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User $useremail called query $query from {$_SERVER['REMOTE_ADDR']}",$logfile);
    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"with parameters: ".var_export($params, true),$logfile);
    // parameterised query has been built in criteria.php, proceed with it
    if($result = $db->execute_query($query, $params)):
        $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
        $queryQty = "SELECT COUNT(*) FROM cards_scry LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id LEFT JOIN `sets` ON cards_scry.setcode = sets.code WHERE ".$criteria;
        // Execute the count query
        if ($countResult = $db->execute_query($queryQty, $params)):
            $row = $countResult->fetch_row();
            $qtyresults = $row[0];
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Query has $qtyresults results",$logfile);

            if ($qtyresults > $maxresults AND $collectionsearch == false):
                $validsearch = "toomany"; //variable set for header.php to display warning
            endif;
        else:
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        endif;
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
                $('#searchsetcode').click(function (event) {
                    if (this.checked) { // check select status of "searchsetcode"
                        $('.notsetcode').each(function () { //deselect "notsetcode"
                            this.checked = false;
                        });
                    }
                });
                $('#yesnotes').click(function (event) {
                    if (this.checked) { // check select status of "yesnotes"
                        $('.notnotes').each(function () { //loop through and deselect each "notnotes" checkbox
                            this.checked = false;
                        });
                    }
                });
                $('#searchpromo').click(function (event) {
                    if (this.checked) { // check select status of "searchpromo"
                        $('.notpromo').each(function () { //loop through and deselect each "notpromo" checkbox
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
                $('.notsetcode').click(function (event) {
                    if (this.checked) { // check select status when clicking on a "notnotes"
                        $('#searchsetcode').each(function () { // deselect "searchsetcode"
                            this.checked = false;
                        });
                    }
                });
                $('.notpromo').click(function (event) {
                    if (this.checked) { // check select status when clicking on a "notpromo"
                        $('#searchpromo').each(function () { // deselect "searchpromo"
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
                    if (this.checked) 
                    {  // check select status of "abilityexact"
                        $('#abilityall').each(function () 
                        { //deselect "abilityall"
                            this.checked = false;
                        });
                    }
                });
                $('.scopecheckbox').click(function (event) {
                    if ($('.scopecheckbox:checked').length === 0) 
                    {
                        $('#cb1').prop("checked", true);
                    };
                });
            });
        </script>
        <?php
        if ((isset($qtyresults)) AND ( $qtyresults != 0)): //Only load these scripts if this is a results call
            // Only load IAS if results are more than a page-full per page type
            if( ($layout == 'bulk' AND ( $qtyresults > $bulkperpage)) OR ($layout == 'list' AND ( $qtyresults > $listperpage)) OR ($layout == 'grid' AND ( $qtyresults > $gridperpage) AND (isset($validsearch) AND ($validsearch !== "toomany")))  ) :   
                // IAS will be needed ?>
                <script src="/js/infinite-ajax-scroll.min.js"></script>
                <script type="text/javascript">
                $(document).ready(function () { // Infinite Ajax Scroll configuration
                    let ias = new InfiniteAjaxScroll('.wrap', {
                        item: '.item', // single items
                        next: '.next',
                        pagination: '.pagination', // page navigation
                        negativeMargin: 250,
                        spinner: {
                            element: '.spinner',
                            delay: 600,
                            show: function(element) {
                                element.style.opacity = '1';
                            },
                            hide: function(element) {
                                element.style.opacity = '0';
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
                <?php
            else: //Results > 0 but < a page, show the No More Results footer at the end ?>
                <script type="text/javascript">
                    $(document).ready(function () {
                        let el = document.querySelector('.ias-no-more');
                        el.style.opacity = '1';
                    });
                </script>
            <?php
            endif; ?>
            <script type="text/javascript">
                $(document).ready(function () {
                    document.body.style.cursor='default';
                    $(".top").hide();
                    var UrlVars = getUrlVars();
                    if (UrlVars["page"] > 1) {
                        $(".top").show();
                    }
                });
            </script>                    
            <script type="text/javascript">
                function isInteger(x) {
                    return x % 1 === 0;
                };
            </script>
            <script type="text/javascript">
                function toggleNoCollectionClass(cardid) {
                    const cellone = document.getElementById('cell' + cardid + '_one');
                    const celltwo = document.getElementById('cell' + cardid + '_two');
                    const cellthree = document.getElementById('cell' + cardid + '_three');
                    var celloneValue = cellone ? cellone.value : 0;
                    var celltwoValue = celltwo ? celltwo.value : 0;
                    var cellthreeValue = cellthree ? cellthree.value : 0;
                    var totalCards = parseInt(celloneValue) + parseInt(celltwoValue) + parseInt(cellthreeValue);
                    const imageID = (cardid + 'img');
                    const image = document.getElementById(cardid + 'img');
                    var checkBox = document.getElementById("float_cview");

                    // Check if the image element exists
                    if (image) {
                        if (totalCards > 0 && checkBox.checked === true) {
                            // Remove the 'none' and 'no_collection' classes
                            image.classList.remove('none', 'no_collection');
                        } else if (totalCards === 0 && checkBox.checked === true) {
                            // Add the 'none' and 'no_collection' classes
                            image.classList.add('none', 'no_collection');
                        } else if (totalCards > 0 && checkBox.checked === false) {
                            image.classList.remove('none');
                        } else if (totalCards === 0 && checkBox.checked === false) {
                            image.classList.add('none');
                        }
                    } else {
                        // Log an error if the image element is not found
                        console.error(`Image element with ID '${cardid}' not found.`);
                    }
                }
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
                                console.log("Success response:", data);
                                activeFlash.classList.remove("bulksubmitsuccessfont");
                                activeFlash.classList.remove("bulksubmiterrorfont");
                                activeFlash.classList.add("bulksubmitnormalfont");
                                activeFlash.classList.add("bulksubmitsuccessbg");
                                setTimeout(function() {
                                  activeFlash.classList.remove("bulksubmitsuccessbg");
                                  activeFlash.classList.add("bulksubmitsuccessfont");
                                }, 2000);
                            },
                            error: function (xhr, status, error) {
                                // Error handling logic here
                                console.error("Error response:", xhr.responseText);
                                var response = JSON.parse(xhr.responseText);
                                activeFlash.classList.remove("bulksubmitsuccessfont");
                                activeFlash.classList.remove("bulksubmitsuccessbg");
                                activeFlash.classList.remove("bulksubmitnormalfont");
                                activeFlash.classList.add("bulksubmiterrorfont");
                            }
                        });
                        console.log("Ajaxupddate: " + cardid);
                        toggleNoCollectionClass(cardid);
                    }
                    return false;
                };
            </script>
            <script type="text/javascript">
                function getUrlVars()
                    {
                        var vars = [], hash; 
                        var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&'); // cut the URL string down to just everything after the ?, split this into an array with information like this: array [0] = "var=xxx"; array [1] = "var2=yyy";
                        //loop through each item in that array
                        for(var i = 0; i < hashes.length; i++)
                        {   //split the item at the "="
                            hash = hashes[i].split('=');
                            //put the value name of the first variable into the "vars" array
                            vars.push(hash[0]);
                            //assign the value to the variable name, now you can access it like this:
                            // alert(vars["var1"]); //alerts the value of the var1 variable from the url string
                            vars[hash[0]] = hash[1];
                        }
                        return vars;
                    }
            </script> <?php 
            if($layout == 'grid' AND isset($validsearch) AND ($validsearch !== "toomany")):  
                $floating_button = true; ?>
                <script>
                    $(document).ready(function(){
                            var sliderElement = document.querySelector('.slider.round.material-symbols-outlined');
                            // Function to remove "no_collection" class from images
                            function removeNoCollectionClass() {
                                // Get all elements with the classes "cardimg none no_collection"
                                var elements = document.querySelectorAll('.cardimg.none.no_collection');

                                // Loop through each element and remove the "no_collection" class
                                elements.forEach(function (element) {
                                    element.classList.remove('no_collection');
                                });
                            }
                            // Function to add "no_collection" class to images
                            function addNoCollectionClass() {
                                // Get all elements with the classes "cardimg none"
                                var elements = document.querySelectorAll('.cardimg.none');

                                // Loop through each element and add the "no_collection" class
                                elements.forEach(function (element) {
                                    element.classList.add('no_collection');
                                });
                            }
                            $('#float_cview').on('change',function(){
                            var checkBox = document.getElementById("float_cview");
                            if (checkBox.checked == true){
                                var cview = "TURN ON";
                                // Call the function to add "no_collection" class
                                addNoCollectionClass();
                                document.getElementById("floating_button_label").title = "Toggle collection view off";
                                sliderElement.classList.remove('book_2');
                            } else {
                                var cview = "TURN OFF";
                                // Call the function to remove "no_collection" class
                                removeNoCollectionClass();
                                document.getElementById("floating_button_label").title = "Toggle collection view on";
                                sliderElement.classList.add('book_2');
                            }
                            $.ajax({  
                                url:"/ajax/ajaxcview.php",  
                                method:"POST",  
                                data:{"collection_view":cview},
                                success:function(data){  
                                //   $('#result').html(data);  
                                }
                            });    
                        });
                    });
                </script> <?php
            endif; 
        endif;?>
    </head>
    <body> <?php 
        include_once("includes/analyticstracking.php");
        $getString = getStringParameters($_GET, 'page'); ?>
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
        else: ?>
            <script>
                // Function to toggle the visibility of the info box
                function toggleInfoBox() {
                    var infoBox = document.getElementById("infoBox");
                    infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "") ? "block" : "none";
                }
            </script>

            <!-- Hovering help button -->
            <div id="info-button" onclick="toggleInfoBox()">
                <span id="help-button" class="material-symbols-outlined">help</span>
            </div>

            <!-- Info box -->
            <div class="info-box" id="infoBox" style="display:none">
                <span class="close-button material-symbols-outlined" onclick="toggleInfoBox()">close</span>
                <div class="info-box-inner">
                    <h2 class="h2-no-top-margin">MtG collection search help</h2>
                    <br><b>Header search</b><br>
                    The quick search in the header will respond with live results to text input<br>
                    Add a setcode in square brackets to restrict the search to a specific set, e.g.:<br>
                    <br>
                    <i>Goblin[m13]</i>
                    <br><br>
                    ...will return Goblins from M13.<br><br>
                    <br><b>Advanced search</b><br>
                    The same [setcode] pattern will work in the main search to search with a name, or ability, etc., e.g. 
                    selecting Ability search and:<br>
                    <br>
                    <i>Haste[m13]</i>
                    <br><br>
                    ...will return cards with the haste keyword from M13.<br><br>
                    "New (7d)" will return cards added in the last 7 days.<br><br>
                    "Promo" will search card promo types, e.g. rainbow foil, etc.
                </div>
            </div> <?php
        endif;
        ?>
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
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']}",$logfile);
                            $setcode = strtolower($row['setcode']);
                            $scryid = $row['cs_id'];
                            if(isset($row['finishes'])):
                                $finishes = json_decode($row['finishes'], TRUE);
                                $cardtypes = cardtypes($finishes);
                            else:
                                $finishes = null;
                                $cardtypes = 'none';
                            endif;
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']} is $cardtypes",$logfile);
                            if (strpos($row['game_types'], 'paper') == false):
                                $not_paper = true;
                            else:
                                $not_paper = false;
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
                            if (empty($row['etched'])):
                                $myetch = 0;
                            else:
                                $myetch = $row['etched'];
                            endif;
                            if(($myqty + $myfoil + $myetch) > 0):
                                $in_collection = ' in_collection';
                            else:
                                $in_collection = '';
                            endif;
                            ?>
                            <div class='gridbox gridboxbulk item'><?php
                                if(stristr($row['name'],' // ') !== false):
                                    $bulkname = substr($row['name'], 0, strpos($row['name'], " // "))." //...";
                                else:
                                    $bulkname = $row['name'];
                                endif;
                                echo "&nbsp;&nbsp;<a class='gridlinkbulk' href='/carddetail.php?id={$row['cs_id']}' tabindex='-1'>{$uppercasesetcode} {$row['number']} $bulkname</a>";
                                $cellid = "cell".$scryid;
                                $cellid_one = $cellid.'_one';
                                $cellid_two = $cellid.'_two';
                                $cellid_three = $cellid.'_three';
                                $cellid_one_flash = $cellid_one;
                                $cellid_two_flash = $cellid_two;
                                $cellid_three_flash = $cellid_three;
                                ?>
                                <table class='bulksubmittable'>
                                    <tr class='bulksubmitrow'>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_one"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "Meld card";
                                            elseif ($not_paper == true):
                                                echo "<i>MtG Arena/Online</i>";
                                            elseif ($cardtypes === 'foilonly'):
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myfoil\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            elseif ($cardtypes === 'etchedonly'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myfoil' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myetch\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myqty\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif;?>
                                        </td>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_two"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'foilonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'etchedonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normaletched'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_two' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_two\",\"$myetch\",\"$cellid_two_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='bulkinput' id='$cellid_two' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellid_two\",\"$myfoil\",\"$cellid_two_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif;?>
                                        </td>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_three"; ?>">
                                            <?php
                                            if ($cardtypes === 'normalfoiletched'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_three' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_three\",\"$myetch\",\"$cellid_three_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                echo "&nbsp;";
                                            endif;?>
                                        </td>
                                    </tr>
                                </table>
                            </div> <?php 
                        endwhile; ?>
                        <div class="ias-no-more">NO MORE RESULTS
                        </div>
                        <div class="spinner"><img src='/images/ajax-loader.gif' alt="LOADING">
                        </div>
                        <!--page navigation--> <?php
                        if (isset($next)):
                            $getString = getStringParameters($_GET, 'page');
                            ?>
                            <div class="pagination"> <?php echo "<a href='index.php{$getString}&amp;page=$next' class='next'>Next</a>"; ?>
                            </div> <?php 
                        endif ?>
                        <table class='bottompad'>
                            <tr>
                                <td>
                                    &nbsp;
                                </td>
                            </tr>
                        </table>    
                    </div> <?php
                elseif ($layout == 'list'):?>
                    <div id='results' class='wrap'>
                        <?php
                        while ($row = $result->fetch_array(MYSQLI_BOTH)) : 
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']}",$logfile);
                            $scryid = $row['cs_id']; ?>
                            <div class='item' style="cursor: pointer;" onclick="location.href='carddetail.php?id=<?php echo $scryid;?>';">
                                <table> <?php
                                    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: $scryid",$logfile);
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
                                            if(isset($row['manacost']) AND !empty($row['manacost'])):
                                                $manac = symbolreplace($row['manacost']);
                                            else:
                                                $manac = NULL;
                                            endif;
                                            ?>
                                        <td class="valuerarity"> <?php echo ucfirst($row['rarity']); ?> </td>
                                        <td class="valueset"> <?php echo $row['set_name']; ?> </td>
                                        <td class="valuetype"> <?php echo $row['type']; ?> </td>
                                        <td class="valuenumber"> <?php echo $row['number']; ?> </td>
                                        <td class="valuemana"> <?php echo $manac; ?> </td>
                                        <td class="valuecollection">
                                            <?php
                                            echo $row['normal'] + $row['foil'] + $row['etched'];
                                            ?>
                                        </td>
                                        <td class="valueabilities"> 
                                            <?php
                                            if(isset($row['ability']) AND !empty($row['ability'])):
                                                $ability = symbolreplace($row['ability']);
                                                echo $ability;
                                            endif;
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
                    </div> <?php 
                elseif ($layout == 'grid') :?>
                    <script type="text/javascript">
                        function swapImage(img_id, card_id, imageurl, imagebackurl) {
                            var ImageId = document.getElementById(img_id);
                            var FrontImg = card_id + ".jpg";
                            var BackImg = card_id + "_b.jpg";

                            if (!ImageId.classList.contains('flipped')) {
                                // If not flipped, apply flip effect
                                ImageId.classList.add('flipped');

                                // Set a timeout for half of the transition duration
                                setTimeout(function () {
                                    ImageId.src = imagebackurl;
                                }, 80);
                            } else {
                                // If already flipped, remove flip effect
                                ImageId.classList.remove('flipped');

                                // Set a timeout for half of the transition duration
                                setTimeout(function () {
                                    ImageId.src = imageurl;
                                }, 80);
                            }
                        }
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
                            if(isset($row['finishes'])):
                                $finishes = json_decode($row['finishes'], TRUE);
                                $cardtypes = cardtypes($finishes);
                            else:
                                $finishes = null;
                                $cardtypes = 'none';
                            endif;
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Current card: {$row['cs_id']} is $cardtypes",$logfile);
                            if (strpos($row['game_types'], 'paper') == false):
                                $not_paper = true;
                            else:
                                $not_paper = false;
                            endif;
                            $uppercasesetcode = strtoupper($setcode);
                            if(($row['p1_component'] === 'meld_result' AND $row['p1_name'] === $row['name']) OR ($row['p2_component'] === 'meld_result' AND $row['p2_name'] === $row['name']) OR ($row['p3_component'] === 'meld_result' AND $row['p3_name'] === $row['name'])):
                                $meld = 'meld_result';
                            elseif($row['p1_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part' OR $row['p2_component'] === 'meld_part'):
                                $meld = 'meld_part';
                            else:
                                $meld = '';
                            endif;
                            $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
                            $imagefunction = $imageManager->getImage($setcode,$row['cs_id'],$ImgLocation,$row['layout'],$two_card_detail_sections);
                            if($imagefunction['front'] == 'error'):
                                $imageurl = '/cardimg/back.jpg';
                            else:
                                $imageurl = $imagefunction['front'];
                            endif;
                            //If page is being loaded by admin, don't cache the image
                            if(($admin == 1) AND ($imageurl !== '/cardimg/back.jpg')):
                                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin loading, don't cache image",$logfile);
                                $imageurl = $imageurl.'?='.$time;
                            endif;
                            if(!is_null($imagefunction['back'])):
                                if($imagefunction['back'] === 'error' OR $imagefunction['back'] === 'error'):
                                    $imagebackurl = '/cardimg/back.jpg';
                                else:
                                    $imagebackurl = $imagefunction['back']."?$time";
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
                            if (empty($row['etched'])):
                                $myetch = 0;
                            else:
                                $myetch = $row['etched'];
                            endif;
                            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Collection view is $collection_view",$logfile);
                            if(($myqty + $myfoil + $myetch) == 0 AND $collection_view == 1):
                                $in_collection = ' none no_collection';
                            elseif(($myqty + $myfoil + $myetch) == 0):
                                $in_collection = ' none';
                            elseif(($myqty + $myfoil + $myetch) > 0 AND $collection_view == 1):
                                $in_collection = '';
                            else:
                                $in_collection = '';
                            endif;
                            ?>
                            <div class='gridbox item'>
                                <?php
                                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." $imageurl",$logfile);
                                if(in_array($row['layout'],$flip_button_cards)):
                                    echo "<div style='cursor: pointer;' class='flipbutton' onclick=swapImage(\"{$img_id}\",\"{$row['cs_id']}\",\"{$imageurl}\",\"{$imagebackurl}\")><span class='material-symbols-outlined refresh'>refresh</span></div>";
                                elseif($row['layout'] === 'flip'):
                                    echo "<div style='cursor: pointer;' class='flipbutton' onclick=rotateImg(\"{$img_id}\")><span class='material-symbols-outlined refresh'>refresh</span></div>";
                                endif;
                                $setname = htmlspecialchars($row['set_name'], ENT_QUOTES);
                                $number_import = $row['number_import'];
                                echo "<a class='gridlink' href='/carddetail.php?id=$scryid'><img id='$img_id' title='$uppercasesetcode ($setname) no. $number_import' class='card-image cardimg$in_collection' alt='$scryid' src='$imageurl'></a>";
                                $cellid = "cell".$scryid;
                                $cellid_one = $cellid.'_one';
                                $cellid_two = $cellid.'_two';
                                $cellid_three = $cellid.'_three';
                                $cellid_one_flash = $cellid_one;
                                $cellid_two_flash = $cellid_two;
                                $cellid_three_flash = $cellid_three;
                                ?>
                                <table class='bulksubmittable'>
                                    <tr class='bulksubmitrow'>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_one"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "Meld card";
                                            elseif ($not_paper == true):
                                                echo "<i>MtG Arena/Online</i>";
                                            elseif ($cardtypes === 'foilonly'):
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myfoil\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            elseif ($cardtypes === 'etchedonly'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myfoil' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myetch\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                $poststring = 'newqty';
                                                echo "Normal: <input class='bulkinput' id='$cellid_one' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$scryid\",\"$cellid_one\",\"$myqty\",\"$cellid_one_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif;?>
                                        </td>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_two"; ?>">
                                            <?php
                                            if($meld === 'meld_result'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'foilonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normalonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'etchedonly'):
                                                echo "&nbsp;";
                                            elseif ($cardtypes === 'normaletched'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_two' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_two\",\"$myetch\",\"$cellid_two_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                $poststring = 'newfoil';
                                                echo "Foil: <input class='bulkinput' id='$cellid_two' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$scryid\",\"$cellid_two\",\"$myfoil\",\"$cellid_two_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            endif;?>
                                        </td>
                                        <td class='bulksubmittd' id="<?php echo $cellid."td_three"; ?>">
                                            <?php
                                            if ($cardtypes === 'normalfoiletched'):
                                                $poststring = 'newetch';
                                                echo "Etch: <input class='bulkinput' id='$cellid_three' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$scryid\",\"$cellid_three\",\"$myetch\",\"$cellid_three_flash\",\"$poststring\");'>";
                                                echo "<input class='card' type='hidden' name='card' value='$scryid'>";
                                            else:
                                                echo "&nbsp;";
                                            endif;?>
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
