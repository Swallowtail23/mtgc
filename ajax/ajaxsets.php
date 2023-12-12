<?php 
/* Version:     1.0
    Date:       12/12/23
    Name:       ajaxsets.php
    Purpose:    PHP script to update sets page
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';
$msg = new Message;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    echo "<table class='ajaxshow'><tr><td class='name'>You are not logged in.</td></tr></table>";
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
    exit(); 
else: 
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'","",$_SESSION['useremail']);
    
    if (isset($_GET['filter'], $_GET['setsPerPage'])):
        $filter = $_GET['filter'];
        $setsPerPage = intval($_GET['setsPerPage']);

        // Calculate the OFFSET based on the page and setsPerPage
        
        $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with filter '$filter', setsPerPage '$setsPerPage'", $logfile);

        // Construct the SQL query with the filter condition and pagination
        $stmt = $db->prepare("SELECT 
                                set_name,
                                setcode,
                                parent_set_code,
                                set_type,
                                card_count,
                                nonfoil_only,
                                foil_only,
                                min(cards_scry.release_date) as date,
                                sets.release_date as setdate
                            FROM cards_scry 
                            LEFT JOIN sets ON cards_scry.set_id = sets.id
                            WHERE sets.code LIKE ? OR sets.parent_set_code LIKE ?
                            OR set_name LIKE ?
                            GROUP BY 
                                set_name
                            ORDER BY 
                                setdate DESC, parent_set_code DESC 
                            LIMIT ?");

        $filter = '%' . $filter . '%'; // Add wildcards to the filter value

        $stmt->bind_param("sssi", $filter, $filter, $filter, $setsPerPage);

        if ($stmt === false):
            echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
            exit();
        endif;

        $exec = $stmt->execute();

        if ($exec === false):
            echo json_encode(['error' => 'Error executing SQL: ' . $db->error]);
            exit();
        else:
            $result = $stmt->get_result();
            $filteredSets = [];

            while ($row = $result->fetch_assoc()):
                // Construct each set data and add it to the array
                $set = [
                    'set_name' => $row['set_name'],
                    'setcode' => $row['setcode'],
                    'parent_set_code' => $row['parent_set_code'],
                    'set_type' => $row['set_type'],
                    'card_count' => $row['card_count'],
                    'nonfoil_only' => $row['nonfoil_only'],
                    'foil_only' => $row['foil_only'],
                    'date' => $row['date'],
                    'setdate' => $row['setdate']
                ];
                $filteredSets[] = $set;
            endwhile;

            $numRows = count($filteredSets);
            $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with filter '$filter', setsPerPage '$setsPerPage': '$numRows' results", $logfile);
            echo json_encode($filteredSets); // Send the filtered sets as JSON response
            exit();
        endif;
    else:
        echo json_encode(['error' => 'No filter, page, or setsPerPage provided']);
        exit();
    endif;
endif;
?>