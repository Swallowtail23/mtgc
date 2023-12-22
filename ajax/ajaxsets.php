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
require ('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message;

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/sets.php'
                            ];

// Normalize the referring page URL
$normalizedReferringPage = str_replace('www.', '', $referringPage);

$isValidReferrer = false;
foreach ($expectedReferringPages as $page):
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false):
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer):

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
        exit(); 
    else: 
        //Need to run these as secpagesetup not run (see page notes)
        $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $useremail = $_SESSION['useremail'];

        if (isset($_GET['filter'], $_GET['setsPerPage'], $_GET['offset']) ):
            $filter = $_GET['filter'];
            $setsPerPage = intval($_GET['setsPerPage']);
            $offset = intval($_GET['offset']);

            $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'", $logfile);
            
            // Filtering filter
            $filtertrim = trim($filter, " \t\n\r\0\x0B");
            $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
            $filter = preg_replace($regex, ' ', $filtertrim);
            $filter = filter_var($filter,FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Filter after URL removal and filtering is '$filter'",$logfile);
            
            if (strlen($filter)< 3 && strlen($filter)!== 0):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Filter not long enough after trimming", $logfile);
                echo json_encode(['error' => 'Filter not long enough after trimming']);
                exit();
            endif;
            
            if ($offset < 0 || $offset > 10000):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Offset not in range", $logfile);
                echo json_encode(['error' => 'Offset not in range']);
                exit();
            endif;
            
            if ($setsPerPage < 2 || $setsPerPage > 100):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Sets per page not in range", $logfile);
                echo json_encode(['error' => 'Sets per page not in range']);
                exit();
            endif;
            
            // Construct the SQL query with the filter condition and WITHOUT pagination
            $stmt = $db->prepare("SELECT setcode
                                FROM cards_scry 
                                LEFT JOIN sets ON cards_scry.set_id = sets.id
                                WHERE sets.code LIKE ? OR sets.parent_set_code LIKE ?
                                OR set_name LIKE ? OR sets.release_date LIKE ?
                                GROUP BY set_name");

            $filter = '%' . $filter . '%'; // Add wildcards to the filter value

            $stmt->bind_param("ssss", $filter, $filter, $filter, $filter);

            if ($stmt === false):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": SQL error: ".$db->error, $logfile);
                echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
                exit();
            endif;

            $exec = $stmt->execute();

            if ($exec === false):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": SQL error: ".$db->error, $logfile);
                echo json_encode(['error' => 'Error executing SQL: ' . $db->error]);
                exit();
            else:
                $result = $stmt->get_result();
                $filteredSets = [];

                while ($row = $result->fetch_assoc()):
                    // Get each set, add to array
                    $set =  [
                            'setcode' => $row['setcode']
                            ];
                    $filteredSets[] = $set;
                endwhile;
                
                $numRows = count($filteredSets);
                $numPages = ceil($numRows / $setsPerPage);
                $stmt->close();
            endif;
            
            // Construct the SQL query with the filter condition and WITH pagination
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
                                OR set_name LIKE ? OR sets.release_date LIKE ?
                                GROUP BY 
                                    set_name
                                ORDER BY 
                                    setdate DESC, parent_set_code DESC 
                                LIMIT ? OFFSET ?");

            $filter = '%' . $filter . '%'; // Add wildcards to the filter value

            $stmt->bind_param("ssssii", $filter, $filter, $filter, $filter, $setsPerPage, $offset);

            if ($stmt === false):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": SQL error: ".$db->error, $logfile);
                echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
                exit();
            endif;

            $exec = $stmt->execute();

            if ($exec === false):
                http_response_code(400);
                $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": SQL error: ".$db->error, $logfile);
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
                
                $currentPage = ($offset / $setsPerPage) + 1;
                $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset': '$numRows' results: '$numPages' pages", $logfile);
                $response = [
                            'numResults' => $numRows,
                            'numPages' => $numPages,
                            'currentPage' => $currentPage,
                            'filteredSets' => $filteredSets,
                            'setsPerPage' => $setsPerPage
                            ];
                echo json_encode($response); // Send the filtered sets as JSON response
                exit();
            endif;
        else:
            http_response_code(400);
            $msg->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Called without required GETS", $logfile);
            echo json_encode(['error' => 'No filter, page, or setsPerPage provided']);
            exit();
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Not called from sets.php (called from $referringPage; expected: $expectedReferringPage)",$logfile);
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>