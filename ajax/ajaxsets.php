<?php

/*
Version:     1.7
Date:        10/01/26
Name:        ajaxsets.php
Purpose:     PHP script to update sets page
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:      -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
include '../includes/colour.php';
$msg = new \MTG\Core\Message($logfile);

$expectedReferringPages = [
    $myURL . '/sets.php'
];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxsets.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request token']);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo json_encode(['error' => 'Access forbidden']);
    endif;
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
    exit();
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];

    if (isset($_GET['filter'], $_GET['setsPerPage'], $_GET['offset'])) :
        $filter = $_GET['filter'];
        $setsPerPage = intval($_GET['setsPerPage']);
        $offset = intval($_GET['offset']);

        $msg->logMessage('[DEBUG]', "Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'");

        // Filtering filter
        $filtertrim = trim($filter, " \t\n\r\0\x0B");
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
        $filter = preg_replace($regex, ' ', $filtertrim);
        $filter = filter_var($filter, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $msg->logMessage('[DEBUG]', "Filter after URL removal and filtering is '$filter'");

        if (strlen($filter) < 3 && strlen($filter) !== 0) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "Filter not long enough after trimming");
            echo json_encode(['error' => 'Filter not long enough after trimming']);
            exit();
        endif;

        if ($offset < 0 || $offset > 10000) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "Offset not in range");
            echo json_encode(['error' => 'Offset not in range']);
            exit();
        endif;

        if ($setsPerPage < 2 || $setsPerPage > 100) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "Sets per page not in range");
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

        if ($stmt === false) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "SQL error: " . $db->error);
            echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
            exit();
        endif;

        $exec = $stmt->execute();

        if ($exec === false) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "SQL error: " . $db->error);
            echo json_encode(['error' => 'Error executing SQL: ' . $db->error]);
            exit();
        else :
                $result = $stmt->get_result();
                $filteredSets = [];

            while ($row = $result->fetch_assoc()) :
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
            $msg->logMessage('[DEBUG]', "Limit: $setsPerPage, Offset: $offset");
            $stmt = $db->prepare(
                "SELECT
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
                    setdate DESC,
                    length(setcode) ASC,
                    length(parent_set_code) ASC,
                    parent_set_code DESC,
                    setcode ASC
                LIMIT ? OFFSET ?"
            );

        $stmt->bind_param("ssssii", $filter, $filter, $filter, $filter, $setsPerPage, $offset);

        if ($stmt === false) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "SQL error: " . $db->error);
            echo json_encode(['error' => 'Error preparing SQL: ' . $db->error]);
            exit();
        endif;

        $exec = $stmt->execute();

        if ($exec === false) :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "SQL error: " . $db->error);
            echo json_encode(['error' => 'Error executing SQL: ' . $db->error]);
            exit();
        else :
                $result = $stmt->get_result();
                $filteredSets = [];

            while ($row = $result->fetch_assoc()) :
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
                $msg->logMessage(
                    '[DEBUG]',
                    "Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset': '$numRows' "
                    . "results: '$numPages' pages"
                );
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
    else :
            http_response_code(400);
            $msg->logMessage('[ERROR]', "Called without required GETS");
            echo json_encode(['error' => 'No filter, page, or setsPerPage provided']);
            exit();
    endif;
endif;
