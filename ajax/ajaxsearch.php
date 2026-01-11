<?php

/*
Version:     7.1
Date:        11/01/26
Name:        ajaxsearch.php
Purpose:     PHP script to run ajax search from header
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:      -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Message;

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new Message($logfile);

$expectedReferringPages = [
    $myURL
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxsearch.php');
if ($ajaxValidation['valid'] === false) :
    $msg->logMessage('[ERROR]', "Not called from valid page");
    ajaxRespondText('Access forbidden', 403);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    //
    if ($_POST) :
        $r = $_POST['search'];
        $rtrim = trim($r, " \t\n\r\0\x0B");
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
        $r = preg_replace($regex, ' ', $rtrim);
        $r = filter_var($r, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        $msg->logMessage('[DEBUG]', "Ajax search after URL removal and filtering is '$r'");
        // Test for the existence of a string enclosed in parentheses
        if (strpos($r, '[') !== false || strpos($r, '(') !== false) :
            $insideBrackets = $closingBracket = $setClosed = false;
            $str = $no = $sc = $typed = '';

            foreach (str_split($r) as $char) :
                if ($char === '[' || $char === '(') :
                    // stop adding to $namestr and trigger insidebrackets
                    $insideBrackets = true;
                elseif ($insideBrackets && $char !== ']' && $char !== ')' && !$setClosed && $char !== ' ') :
                        // inside brackets, set not closed, no space... this is setcode
                        $sc .= $char;
                elseif ($insideBrackets && $char !== ']' && $char !== ')' && $char === ' ' && !$setClosed) :
                        // inside brackets, space - setcode finished
                        $setClosed = true;
                elseif ($insideBrackets && $char !== ']' && $char !== ')' && $setClosed === true) :
                        // inside brackets, set closed - this is number
                        $no .= $char;
                elseif ($insideBrackets && ($char === ']' || $char === ')')) :
                        // closing bracket
                        $setClosed = true;
                        $closingBracket = true;
                        break;
                elseif (!$insideBrackets) :
                        $str .= $char;
                else :
                        $msg->logMessage('[DEBUG]', "Should not be in here...");
                endif;
            endforeach;
            if ($insideBrackets && !$setClosed) :
                $setcode = trim($sc) . '%';
                $number = '';
            elseif ($setClosed && $no === '' && $closingBracket) :
                    $setcode = trim($sc);
                    $number = '';
            elseif ($insideBrackets && $no !== '' && !$closingBracket) :
                    $setcode = trim($sc);
                    $number = trim($no) . '%';
            elseif ($setClosed && $no !== '' && $closingBracket) :
                    $setcode = trim($sc);
                    $number = trim($no);
            endif;
                $typed = trim($str);
                $searchString = '%' . trim($str) . '%';
                // Here $typed is the text typed
            if (isset($setcode)) :
                if (isset($number)) :
                    $teststring = trim(trim($setcode) . " " . trim($number));
                else :
                        $teststring = trim($setcode);
                endif;
                    $msg->logMessage('[DEBUG]', "Testing '$teststring' against Brackets list");
                if (isset($teststring) && inArrayCaseInsensitive($teststring, $bracketsInNames)) :
                    $msg->logMessage(
                        '[DEBUG]',
                        "Bracket contents match a card with brackets in name, resetting name, set to match"
                    );
                    $searchString = $typed = $typed . " (" . $teststring . ")";
                    $setcode = $number = '';
                endif;
            endif;
        else :
                // No brackets in this case
                $typed = trim($r);
                $searchString = '%' . trim($r) . '%';
                $setcode = '';
                $number = '';
        endif;
            $msg->logMessage(
                '[DEBUG]',
                "Typed: '$typed'; Search: '$searchString'; Setcode: '$setcode'; Number: '$number' "
            );

        // Header search only searches within primary_card set, not additional languages
        $query = "SELECT id, setcode, name, printed_name, flavor_name, f1_name, f1_printed_name, "
            . "f1_flavor_name, f2_name, f2_printed_name, f2_flavor_name, release_date "
            . "FROM cards_scry "
            . "WHERE "
            . "(printed_name LIKE ? "
            . "OR flavor_name LIKE ? "
            . "OR name LIKE ? "
            . "OR f1_printed_name LIKE ? "
            . "OR f1_flavor_name LIKE ? "
            . "OR f1_name LIKE ? "
            . "OR f2_printed_name LIKE ? "
            . "OR f2_flavor_name LIKE ? "
            . "OR f2_name LIKE ?) "
            . "AND "
            . "(setcode LIKE ? OR ? = '') "
            . "AND "
            . "(number_import LIKE ? or ? = '') "
            . "AND "
            . "(primary_card = 1) "
            . "ORDER BY release_date DESC, name ASC LIMIT 20";
        $stmt = $db->prepare($query);
        $stmt->bind_param(
            "sssssssssssss",
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $searchString,
            $setcode,
            $setcode,
            $number,
            $number
        );
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result(
            $id,
            $setcode,
            $name,
            $printed_name,
            $flavor_name,
            $f1_name,
            $f1_printed_name,
            $f1_flavor_name,
            $f2_name,
            $f2_printed_name,
            $f2_flavor_name,
            $release_date
        );

        if ($stmt->error) :
            throw new Exception(
                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $stmt->error
            );
        else : ?>
                <table class='ajaxshow'> <?php
                while ($row = $stmt->fetch()) :
                    if ($printed_name !== null and strpos(strtolower($printed_name), strtolower($typed)) !== false) :
                        $name = $printed_name;
                    elseif ($flavor_name !== null and strpos(strtolower($flavor_name), strtolower($typed)) !== false) :
                        $name = $flavor_name;
                    elseif ($f1_name !== null and strpos(strtolower($f1_name), strtolower($typed)) !== false) :
                        $name = $f1_name;
                    elseif (
                        $f1_printed_name !== null
                        and strpos(strtolower($f1_printed_name), strtolower($typed)) !== false
                    ) :
                        $name = $f1_printed_name;
                    elseif (
                        $f1_flavor_name !== null
                        and strpos(strtolower($f1_flavor_name), strtolower($typed)) !== false
                    ) :
                        $name = $f1_flavor_name;
                    elseif ($f2_name !== null and strpos(strtolower($f2_name), strtolower($typed)) !== false) :
                        $name = $f2_name;
                    elseif (
                        $f2_printed_name !== null
                        and strpos(strtolower($f2_printed_name), strtolower($typed)) !== false
                    ) :
                        $name = $f2_printed_name;
                    elseif (
                        $f2_flavor_name !== null
                        and strpos(strtolower($f2_flavor_name), strtolower($typed)) !== false
                    ) :
                        $name = $f2_flavor_name;
                    endif;
                    $displaysetcode = strtoupper($setcode);
                    $query = "SELECT id, number_import FROM cards_scry WHERE id LIKE ? LIMIT 1";
                    $params = [$id];
                    $result = $db->execute_query($query, $params);
                    if ($result === false) :
                        throw new Exception(
                            "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $db->error
                        );
                    else :
                        $row = $result->fetch_assoc();
                        if ($row) :
                            $ajaxid = $row['id'];
                            $ajaxnumber = $row['number_import'];
                            $b_name = '<strong>' . $typed . '</strong>';
                            $final_name = str_ireplace($typed, $b_name, (string)$name);
                            ?>
                                <tr>
                                    <td title='<?php echo "$displaysetcode - $name" ?>' class="name">
                                        <?php
                                        echo "<a href='carddetail.php?id=$ajaxid'>$displaysetcode - "
                                            . "$final_name</a></td>";
                                        ?>
                                    </td>
                                </tr>
                                <?php
                        endif;
                    endif;
                endwhile; ?>
                </table> <?php
        endif;
    endif;
endif;
?>
