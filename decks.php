<?php 
/* Version:     5.0
    Date:       25/03/23
    Name:       decks.php
    Purpose:    Main decks list page
    Notes:       
    To do:      
    
    1.0
                Initial version
 *  2.0 
 *              Bug fixes (prevent adding deck with blank name)
 *  3.0 
 *              Mysqli_Manager conversion
 *  3.1
 *              Moved from writelog to Message class
 *  4.0 
 *              Refactoring for cards_scry data
 *  5.0
 *              PHP 8.1 compatibility
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password

//page specific variables
$newdeck        = isset($_POST['newdeck']) ? 'yes' : '';
$deckname       = isset($_POST['deckname']) ? filter_input(INPUT_POST, 'deckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES): '';
$deletedeck     = isset($_POST['deletedeck']) ? 'yes' : '';
$decktodelete   = isset($_POST['decktodelete']) ? filter_input(INPUT_POST, 'decktodelete', FILTER_SANITIZE_NUMBER_INT):'';
?> 
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title> MtG collection - Decks</title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
    <script type="text/javascript">
        jQuery( function($) {
            $('tbody tr[data-href]').addClass('clickable').click( function() {
            window.location = $(this).attr('data-href');
             });
        });
    </script>
    <script type="text/javascript">
        $(function() {
            $("#deletedeck").submit(function(event){
                if (!confirm("Confirm OK to delete deck?")){
                    event.preventDefault();
                }
            });
        });
    </script>
    <script type="text/javascript"> 
        function CloseMe( obj )
            {
                obj.style.display = 'none';
            }
    </script>
    <script type="text/javascript">
        function createready() {
            var newdeckname = document.getElementById("newdeckname");
            var createsubmit = document.getElementById("createsubmit");
            if(newdeckname.value==="") { 
                createsubmit.disabled = true; 
                createsubmit.style.cursor = 'not-allowed';
                createsubmit.classList.remove('inline_button');
                createsubmit.classList.add('inline_button_disabled');
            } else { 
                createsubmit.disabled = false;
                createsubmit.style.cursor = 'pointer';
                createsubmit.classList.add('inline_button');
                createsubmit.classList.remove('inline_button_disabled');
            }
        }
    </script>
    <script type="text/javascript">
        function deleteready() {
            var deckselect = document.getElementById("deckselect");
            var deletebutton = document.getElementById("deletebutton");
            if(deckselect.value==="Pick one") { 
                deletebutton.disabled = true; 
                deletebutton.style.cursor = 'not-allowed';
                deletebutton.classList.remove('inline_button');
                deletebutton.classList.add('inline_button_disabled');
            } else { 
                deletebutton.disabled = false;
                deletebutton.style.cursor = 'pointer';
                deletebutton.classList.add('inline_button');
                deletebutton.classList.remove('inline_button_disabled');
            }
        }
    </script>
</head>

<body class="body">
<?php include_once("includes/analyticstracking.php");    
// Start building the page here, so errors show in the website template
// Includes first - menu and header            
require('includes/overlays.php');
require('includes/header.php'); 
require('includes/menu.php'); //mobile menu

// Next the main DIV section 
?>
<div id="page">
    <div class="staticpagecontent">
        <?php
        // Create a new deck
        if($newdeck == "yes"):
            if($deckname == ''):
                ?>
                <div class="msg-new error-new" onclick='CloseMe(this)'><span>Name can't be empty</span>
                    <br>
                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                </div>
                <?php
            else:
                if ($checkunique = $db->select('decknumber','decks',"WHERE owner = $user AND deckname = '$deckname' LIMIT 1")):
                    if ($checkunique->num_rows > 0):
                        ?>
                        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Name already used</span>

                            <br>
                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                        </div>
                        <?php
                    else:
                        //Create new deck
                        $data = array(
                            'owner' => $user,
                            'deckname' => $deckname
                        );
                        if($db->insert('decks',$data) === TRUE):
                            if ($checkcreated = $db->select('decknumber','decks',"WHERE owner = $user AND deckname = '$deckname' LIMIT 1")):
                                if ($checkcreated->num_rows !== 1):
                                    trigger_error('Error: Deck creation validation check failed', E_USER_ERROR);
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck $deckname created ",$logfile);
                                endif;    
                            else:
                                trigger_error('Error: Deck creation validation check failed', E_USER_ERROR);
                            endif;
                        else:
                            trigger_error('Error: Deck creation SQL error', E_USER_ERROR);
                        endif;
                    endif;
                else:
                    trigger_error('Error: Deck exists check SQL error', E_USER_ERROR);
                endif;
            endif;
        endif;
        
        // Delete a deck
        if($deletedeck == "yes"):
            deldeck($decktodelete);
        endif;
        // List decks
        ?>
        <div id='decklistdiv'>
        <h2 class='h2pad'>My Decks</h2>
        <?php
        if($sqlquery = $db->select('*','decks',"WHERE owner = $user ORDER BY type ASC, deckname ASC")):?>
            <table class="decklist">
                <?php
                $typeheader = '';
                while ($row = $sqlquery->fetch_assoc()):
                    if($row['type'] == NULL):
                        $row['type'] = 'Not set';
                    endif;
                    if($typeheader == '' OR $row['type'] != $typeheader):
                        echo "<tr><td><b>{$row['type']}</b></td></tr>";
                        $typeheader = $row['type']; 
                    endif;?>
                    <tr class='resultsrow' style='cursor: pointer;' <?php echo "data-href='deckdetail.php?deck={$row['decknumber']}'"; ?>>
                    <?php echo "<td class='decklist_name'>".$row['deckname']."</td>"; ?>
                    </tr>
                <?php 
                endwhile;?>
            </table>
            </div>
            <div id='deckoperations'>
            <h3>Add a new deck</h3>
            <form name="newdeck" action="decks.php" method="post">
                <input type='hidden' name="newdeck" value="yes">
                <input class='textinput' onkeyup='createready()' title="Please enter deck title" placeholder="DECK TITLE" id="newdeckname" name="deckname" type="text" size="24" maxlength="150" /><br><br>
                <input class='inline_button_disabled stdwidthbutton' id="createsubmit" style='cursor: not-allowed;' type="submit" value="CREATE DECK" disabled/>
            </form>
            <h3>Delete a deck</h3>
            <form id="deletedeck" action="decks.php" method="POST">
                <input type='hidden' name="deletedeck" value="yes">
                <select id='deckselect' name='decktodelete' onchange='deleteready()'>
                    <option selected='selected' disabled='disabled'>Pick one</option>
                    <?php 
                    mysqli_data_seek($sqlquery, 0);
                    while ($row = $sqlquery->fetch_assoc()):
                        echo "<option value='{$row['decknumber']}'>{$row['deckname']}</option>";
                    endwhile;
                    ?>
                </select><br><br>
                <input class='inline_button_disabled stdwidthbutton' style='cursor: not-allowed;' id="deletebutton" type="submit" value="DELETE DECK" disabled>
            </form>
            <br> &nbsp;
        <?php
        else:
            trigger_error('[ERROR] decks.php: List decks SQL error', E_USER_ERROR);
        endif;
        ?>
        </div>
    </div>
</div>

<?php require('includes/footer.php'); ?>        
</body>
</html>
