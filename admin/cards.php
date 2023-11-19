<?php
/* Version:     5.0
    Date:       25/03/2023
    Name:       admin/cards.php
    Purpose:    Card administrative tasks
    Notes:      This page uses a combination of Mysqli prepared statements and straight OOP mysqli connectivity
                using $db (Mysqli_Manager).
        
    1.0
                Initial version - no function yet
 *  2.0         
 *              Functions added for add, edit, copy cards; run legality check; add pre-release promos
 *  3.0
 *              Move from writelog to Message class
 *  4.0     
 *              Much simpler form, all data from Scryfall, so no editing here - just delete or delete image
 *  5.0
 *              PHP 8.1 compatibility
*/

session_start();
require ('../includes/ini.php');                //Initialise and load ini file
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');      //Includes basic functions for non-secure pages
require ('adminfunctions.php');
require ('../includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                                  //Check if user is disabled or needs to change password


//Check if user is logged in, if not redirect to login.php
$obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Admin page called by user $username ($useremail)",$logfile);

// Is admin running the page
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin is $admin",$logfile);
if ($admin !== 1):
    require('reject.php');
endif;

// Find if this card is in any decks
if(isset($_GET['cardtoedit'])): 
    $id = filter_input(INPUT_GET, 'cardtoedit', FILTER_SANITIZE_SPECIAL_CHARS);

    $sql = "SELECT deckname, username FROM decks
            LEFT JOIN users ON decks.owner = users.usernumber
            LEFT JOIN deckcards ON decks.decknumber = deckcards.decknumber
            WHERE deckcards.cardnumber = ?";

    $stmt = $db->prepare($sql);
    if ($stmt):
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $stmt->bind_result($deckname, $deckowner);

    else:
        trigger_error("[ERROR] cards.php: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
    endif;
    while ($stmt->fetch()):
        $resultArray[] = array('deckname' => $deckname, 'deckowner' => $deckowner);
    endwhile;
    $stmt->close();
    
    $sql2 = "SELECT usernumber,username FROM users";
    $stmt = $db->prepare($sql2);
    if ($stmt):
        $stmt->execute();
        $stmt->bind_result($usernumber, $username);
    else:
        trigger_error("[ERROR] cards.php: Wrong SQL: ($sql2) Error: " . $db->error, E_USER_ERROR);
    endif;
    while ($stmt->fetch()):
        $userResultArray[] = array('usernumber' => $usernumber, 'username' => $username);
    endwhile;
    $stmt->close();

    foreach($userResultArray as $userArray):
        $table = $userArray['usernumber']."collection";
        $sql = "SELECT SUM(COALESCE(`$table`.`normal`, 0) + COALESCE(`$table`.`foil`, 0) + COALESCE(`$table`.`etched`, 0)) AS total FROM `$table` WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        // Check if the statement was prepared successfully
        if ($stmt):
            $stmt->bind_param("s", $id);
            if ($stmt->error) {
                trigger_error("[ERROR] Bind error: " . $stmt->error, E_USER_ERROR);
            }
            $stmt->execute();
            $stmt->bind_result($total);
        else:
            trigger_error("[ERROR] cards.php: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
        endif;
        while ($stmt->fetch()):
            if($total !== NULL AND $total != 0):
                $collectionResultArray[] = array('owner' => $userArray['username'], 'total' => $total);
            endif;
        endwhile;
        $stmt->close();
    endforeach;
    
    $sql3 = "SELECT performed_at,migration_strategy,new_scryfall_id,note,metadata_name,uri,metadata_collector_number,db_match FROM migrations WHERE old_scryfall_id = ?";
    $stmt = $db->prepare($sql3);
    if ($stmt):
        $stmt->bind_param("s", $id);
            if ($stmt->error) {
                trigger_error("[ERROR] Bind error: " . $stmt->error, E_USER_ERROR);
            }
            $stmt->execute();
        $stmt->bind_result($date,$strategy,$new_id,$migration_note,$migration_name,$migration_uri,$migration_coll_number,$db_match,);
    else:
        trigger_error("[ERROR] cards.php: Wrong SQL: ($sql3) Error: " . $db->error, E_USER_ERROR);
    endif;
    while ($stmt->fetch()):
        $migrationResultArray[] = array('date' => $date, 'strategy' => $strategy, 'new_id' => $new_id, 'migration_note' => $migration_note, 'migration_name' => $migration_name, 'migration_uri' => $migration_uri, 'migration_coll_number' => $migration_coll_number, 'db_match' => $db_match);
    endwhile;
    $stmt->close();
endif;
if ((isset($_GET['delete'])) AND ( $_GET['delete'] == 'DELETE')):
    if (isset($_GET['id'])):
        $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete card $id called by $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
    $sql = "DELETE FROM cards_scry WHERE id = '$id'";
    $result = $db->query($sql);
    if ($result === false):
        trigger_error("[ERROR] cards.php: Deleting card: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
    else:
        $sql = "SELECT id FROM cards_scry WHERE id = '$id'";
        $result = $db->query($sql);
        $rowcount = $result->num_rows;
        if ($result === false):
            trigger_error("[ERROR] cards.php: Deleting card: Wrong SQL: ($sql) Error: " . $db->error, E_USER_ERROR);
        elseif ($rowcount === 0):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Delete card $id successful",$logfile);
            ?>
            <div class="alert-box success" id="setdeletealert2"><span>success: </span>Deleted</div> <?php
        endif;
    endif;
elseif ((isset($_GET['deleteimg'])) AND ( $_GET['deleteimg'] == 'DELETEIMG')):
    if (isset($_GET['id'])):
        $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS);
    endif;
    refresh_image($id);
endif;
?>

<!DOCTYPE html>
<head>
    <title>MtG collection administration - cards</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver ?>.css">
    <?php include('../includes/googlefonts.php'); ?>
</head>
<body id="body" class="body">    
   
<?php
include '../includes/overlays.php';
include '../includes/header.php';
require('../includes/menu.php');
?>
    <div id='page'>
        <div class='staticpagecontent'>
            <div> <?php 
                if(isset($_GET['cardtoedit'])):  ?>
                    <h3>Delete cards / images</h3>
                    <?php echo "Card id loaded: $id"; ?>
                        <form id='carddeleteform' action="?" method="GET">
                            <input type='hidden' name='id' value='<?php echo "$id";?>' >
                            <input class='inline_button stdwidthbutton updatebutton' id='deletebutton' name='delete' type="submit" value="DELETE" 
                                   onclick="return confirm('Do you really want to delete this card?');">
                        </form>
                        <form id='cardimgdeleteform' action="?" method="GET">
                            <input type='hidden' name='id' value='<?php echo "$id";?>' >
                            <button class='inline_button stdwidthbutton updatebutton' id='deleteimgbutton' name='deleteimg' type="submit" value="DELETEIMG" 
                                   onclick="return confirm('Do you really want to delete this card image?');">DELETE IMAGE</button>
                    </form>  <?php
                        // Fetch and print the results
                        
                        if (isset($migrationResultArray) AND !empty($migrationResultArray)):
                            echo '<h3>Card in migration list</h3>';
                            echo '<table border="1">';
                            echo '<tr><th>Date</th><th>Strategy</th><th>New ID</th><th>Note</th><th>Migration name</th><th>Details URI</th><th>Collector number</th><th>DB match</th></tr>';

                            foreach ($migrationResultArray as $result):
                                echo '<tr>';
                                echo '<td>' . $result['date'] . '</td>';
                                echo '<td>' . $result['strategy'] . '</td>'; ?>
                                <td>
                                    <a target="_blank" href='/carddetail.php?id=<?php echo $result['new_id'];?>'>
                                         <?php echo $result['new_id']; ?> 
                                    </a>
                                </td>
                                <?php
                                echo '<td>' . $result['migration_note'] . '</td>';
                                echo '<td>' . $result['migration_name'] . '</td>'; ?>
                                <td>
                                    <a target="_blank" href='<?php echo $result['migration_uri'];?>'>
                                         <?php echo $result['migration_uri']; ?> 
                                    </a>
                                </td>
                                <?php
                                echo '<td>' . $result['migration_coll_number'] . '</td>';
                                echo '<td>' . $result['db_match'] . '</td>';
                                echo '</tr>';
                            endforeach;
                            echo '</table>';
                        endif;
                        echo '<h3>Card in decks</h3>';
                        if (!empty($resultArray)):
                            echo '<table border="1">';
                            echo '<tr><th>Deck Name</th><th>Owner</th></tr>';

                            foreach ($resultArray as $result):
                                echo '<tr>';
                                echo '<td>' . $result['deckname'] . '</td>';
                                echo '<td>' . $result['deckowner'] . '</td>';
                                echo '</tr>';
                            endforeach;

                            echo '</table>';
                        else:
                            echo 'None';
                        endif;
                        echo '<h3>Card in collections</h3>';
                        if (isset($collectionResultArray) AND !empty($collectionResultArray)):
                            echo '<table border="1">';
                            echo '<tr><th>Owner</th><th>Quantity</th></tr>';

                            foreach ($collectionResultArray as $result):
                                if($result['total'] !== 0 AND $result['total'] !== null):
                                    echo '<tr>';
                                    echo '<td>' . $result['owner'] . '</td>';
                                    echo '<td>' . $result['total'] . '</td>';
                                    echo '</tr>';
                                endif;
                            endforeach;

                            echo '</table>';
                        else:
                            echo 'None';
                        endif;
        
                elseif(isset($imageurl) AND $imageurl !== '' AND $imagedelete === 'success'):
                    echo "<h3>Image delete processed</h3>";
                    echo "$imageurl deleted";
                    if(isset($imagebackdelete)):
                        echo "$imagebackurl deleted";
                    endif;
                    echo "<meta http-equiv='refresh' content='2;url=cards.php'>";
                elseif(isset($imageurl) AND $imageurl !== '' AND $imagedelete === 'failure'):
                    echo "<h3>Image delete NOT processed</h3>";
                    echo "$imagedelete $imageurl NOT deleted";
                else:
                    echo "<h3>Load this page from a card details page to delete a card or its image</h3>";
                endif; ?>
            </div>
        </div>
    </div>
<?php
require('../includes/footer.php'); ?>
</body>
</html>