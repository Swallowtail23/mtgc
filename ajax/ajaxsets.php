<?php 
/* Version:     1.1
    Date:       20/01/24
    Name:       ajaxsets.php
    Purpose:    PHP script to update sets page
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 
    1.1         20/01/24
 *              Include sessionname.php and move to logMessage
 *
 *  1.2         29/05/24
 *              Fix incorrect set ordering
*/
if (file_exists('../includes/sessionname.local.php')):
    require('../includes/sessionname.local.php');
else:
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message($logfile);

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

            $msg->logMessage('[DEBUG]',"Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'");
            
            // Filtering filter
            $filtertrim = trim($filter, " \t\n\r\0\x0B");
            $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
            $filter = preg_replace($regex, ' ', $filtertrim);
            $filter = filter_var($filter,FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $msg->logMessage('[DEBUG]',"Filter after URL removal and filtering is '$filter'");
            
            if (strlen($filter)< 3 && strlen($filter)!== 0):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"Filter not long enough after trimming");
                echo json_encode(['error' => 'Filter not long enough after trimming']);
                exit();
            endif;
            
            if ($offset < 0 || $offset > 10000):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"Offset not in range");
                echo json_encode(['error' => 'Offset not in range']);
                exit();
            endif;
            
            if ($setsPerPage < 2 || $setsPerPage > 100):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"Sets per page not in range");
                echo json_encode(['error' => 'Sets per page not in range']);
                exit();
            endif;
            
            // Construct the SQL query with the filter condition and WITHOUT pagination
            $stmt = $db->prepare("SELECT code as setcode
                                FROM sets 
                                WHERE code LIKE ? OR parent_set_code LIKE ?
                                OR name LIKE ? OR release_date LIKE ?
                                GROUP BY name");

            $filter = '%' . $filter . '%'; // Add wildcards to the filter value

            $stmt->bind_param("ssss", $filter, $filter, $filter, $filter);

            if ($stmt === false):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"SQL error: ".$db->error);
                echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
                exit();
            endif;

            $exec = $stmt->execute();

            if ($exec === false):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"SQL error: ".$db->error);
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
            $msg->logMessage('[DEBUG]',"Limit: $setsPerPage, Offset: $offset");
            $stmt = $db->prepare("SELECT 
                                    name as set_name,
                                    code as setcode,
                                    parent_set_code,
                                    set_type,
                                    card_count,
                                    nonfoil_only,
                                    foil_only,
                                    min(release_date) as date,
                                    release_date as setdate
                                FROM sets
                                WHERE code LIKE ? OR parent_set_code LIKE ?
                                OR name LIKE ? OR release_date LIKE ?
                                GROUP BY 
                                    name
                                ORDER BY 
                                    setdate DESC, length(setcode) ASC, length(parent_set_code) ASC, parent_set_code DESC, setcode ASC
                                LIMIT ? OFFSET ?");

            $stmt->bind_param("ssssii", $filter, $filter, $filter, $filter, $setsPerPage, $offset);

            if ($stmt === false):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"SQL error: ".$db->error);
                echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
                exit();
            endif;

            $exec = $stmt->execute();

            if ($exec === false):
                http_response_code(400);
                $msg->logMessage('[ERROR]',"SQL error: ".$db->error);
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
                $msg->logMessage('[DEBUG]',"Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset': '$numRows' results: '$numPages' pages");
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
            $msg->logMessage('[ERROR]',"Called without required GETS");
            echo json_encode(['error' => 'No filter, page, or setsPerPage provided']);
            exit();
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from sets.php (called from $referringPage; expected: $expectedReferringPage)");
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>