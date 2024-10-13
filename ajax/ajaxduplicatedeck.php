<?php 
/* Version:     1.0
    Date:       05/10/24
    Name:       ajaxduplicatedeck.php
    Purpose:    PHP script to duplicate deck
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -
    1.0         Initial version
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

// Check if the request is coming from a valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages = [$myURL . '/deckdetail.php'];

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
    $response = ['success' => false, 'error' => ''];
    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        $response['success'] = false;
        $response['error'] = 'User not logged in';
        returnResponse(); 
    else: 
        // Need to run these as secpagesetup is not run (see page notes)
        $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $useremail = $_SESSION['useremail'];

        if (isset($_POST['user']) && isset($_POST['deckname']) && isset($_POST['decknumber']) && isset($_POST['decktype'])):
            $user = filter_input(INPUT_POST, 'user', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $deckname = filter_input(INPUT_POST, 'deckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $decknumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $decktype = filter_input(INPUT_POST, 'decktype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $msg->logMessage('[ERROR]',"Call to duplicate user $user's deck number $decknumber, $deckname ($decktype)");
            $counter = 1;
            $newdeckname = $deckname . "_$counter";
            
            do {
                // Check if the deck name already exists
                $decknamechecksql = "SELECT decknumber FROM decks WHERE owner = ? and deckname = ? LIMIT 1";
                $decknameparams = [$user, $newdeckname];
                $result = $db->execute_query($decknamechecksql, $decknameparams);

                if ($result !== false && $result->num_rows > 0):
                    // Increment the counter and create a new name
                    $counter++;
                    $newdeckname = $deckname . "_$counter";  // Ensure that only one counter is appended
                endif;
            } while ($result !== false && $result->num_rows > 0);

            // Instantiate the DeckManager
            $obj = new DeckManager($db, $logfile, $useremail, $serveremail, $importLinestoIgnore, $nonPreferredSetCodes);
            
            //Create the new deck shell
            $decksuccess = $obj->addDeck($user, $newdeckname);
            $msg->logMessage('[DEBUG]',"Created deck number {$decksuccess['decknumber']}");
            
            //get the cardlist from the source deck
            $cardlist = $obj->exportDeck($decknumber, "variable");
            $msg->logMessage('[DEBUG]',"Cardlist: $cardlist");
            
            //Set the decktype the same as the source deck
            $setdecktype = $obj->setDeckType($decksuccess['decknumber'],$decktype);
            if($setdecktype !== 0):
                $response['success'] = false;
                $response['error'] = 'Deck type set failed';
                returnResponse(); 
            endif;
            
            //import the card list to the new deck
            $obj->processInput($decksuccess['decknumber'],$cardlist);

            if ($decksuccess['flag'] === 1 && $cardlist !== '' && $setdecktype === 0):
                $response['success'] = true;
                $response['decknumber'] = $decksuccess['decknumber'];
                returnResponse(); 
            else:
                $response['success'] = false;
                $response['error'] = 'Failed to duplicate deck';
                returnResponse(); 
            endif;
        else:
            $response['success'] = false;
            $response['error'] = 'Invalid input';
            returnResponse(); 
        endif;
    endif;
else:
    // Log the error and return forbidden response as JSON
    $msg->logMessage('[ERROR]',"Not called from a valid page");
    http_response_code(403);
    $response['success'] = false;
    $response['error'] = 'Access forbidden';
    returnResponse(); 
endif;

// Function to echo JSON response and exit
function returnResponse()
{
    global $response;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>