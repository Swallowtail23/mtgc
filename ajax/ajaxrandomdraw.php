<?php 
/* Version:     1.0
    Date:       10/06/24
    Name:       ajaxrandomdraw.php
    Purpose:    PHP script to generate random hand draws for decks
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0         10/06/24
                Initial version
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'):
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
    
    $referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $expectedReferringPages =   [
                                    $myURL . '/deckdetail.php'
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
    
    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
        exit(); 
    else: 
        // Decode the JSON data received from the POST request
        $data = json_decode(file_get_contents('php://input'), true);

        // Check if the required variables are present
        if (isset($data['uniquecard_ref']) && isset($data['include_check']) && $data['include_check'] === true && $isValidReferrer === true):
            $uniquecard_ref = $data['uniquecard_ref'];
        else:
            exit;
        endif;
    endif;
else:
    if(!defined('INCLUDE_CHECK')):
        die('Direct access prohibited');
    endif;
    
    if (!isset($uniquecard_ref)):
        // do nothing
    endif;
endif;

$a = array_rand($uniquecard_ref, 7);
echo "<table>";
echo "<tr><td>&nbsp;</td></tr>";
for ($i = 0; $i < 7; $i++) {
    echo "<tr><td>" . ($i + 1) . ": " . $uniquecard_ref[$a[$i]]['name'] . "</td></tr>";
}
echo "</table>";