<?php 
/* Version:     6.0
    Date:       07/07/24
    Name:       ajaxsearch.php
    Purpose:    PHP script to run ajax search from header
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 *  2.0
 *              Migrated to Mysqli_Manager
 *  2.1
 *              Moved from writelog to Message class
 *  3.0
 *              Refactor for cards_scry
 *  4.0
 *              Move to prepared statements
 *  5.0
 *              Add [set] search interpretation
 * 
 *  5.1         20/01/24
 *              Include sessionname.php and move to logMessage
 *
 *  6.0         07/07/24
 *              Improved resilience and search options, including () and [], 
 *              cards with part-names in brackets, etc
 *              
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

$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPage = $myURL;

$normalizedReferringPage = str_replace('www.', '', $referringPage);
$normalizedExpectedReferringPage = str_replace('www.', '', $expectedReferringPage);

if (strpos($normalizedReferringPage, $normalizedExpectedReferringPage) !== false):

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
        exit(); 
    else: 
        //Need to run these as secpagesetup not run (see page notes)
        $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        //
        if($_POST):
            $r = $_POST['search'];
            $rtrim = trim($r, " \t\n\r\0\x0B");
            $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
            $r = preg_replace($regex, ' ', $rtrim);
            $r = filter_var($r,FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
            $msg->logMessage('[DEBUG]',"Ajax search after URL removal and filtering is '$r'");        
            // Test for the existence of a string enclosed in parentheses
            if (strpos($r, '[') !== false || strpos($r, '(') !== false):
                $insideBrackets = $closingBracket = $setClosed = false;
                $str = $no = $sc = $typed = '';
                
                foreach (str_split($r) as $char):
                    if ($char === '[' || $char === '('): // stop adding to $namestr and trigger insidebrackets
                        $insideBrackets = true;
                    elseif ($insideBrackets && $char !== ']' && $char !== ')' && !$setClosed && $char !== ' '): // inside brackets,  set not closed, no space... this is setcode
                        $sc .= $char;
                    elseif ($insideBrackets && $char !== ']' && $char !== ')'&& $char === ' ' && !$setClosed):  // inside brackets, space - setcode finished
                        $setClosed = true;
                    elseif ($insideBrackets && $char !== ']' && $char !== ')'&& $setClosed === true):  // inside brackets, set closed - this is number
                        $no .= $char;
                    elseif ($insideBrackets && ($char === ']' || $char === ')')):  // closing bracket
                        $setClosed = true;
                        $closingBracket = TRUE;
                        break;
                    elseif (!$insideBrackets):
                        $str .= $char;
                    else:
                        $msg->logMessage('[DEBUG]',"Should not be in here...");
                    endif;
                endforeach;
                if($insideBrackets && !$setClosed):
                    $setcode = trim($sc).'%';
                    $number = '';
                elseif($setClosed && $no === '' && $closingBracket):
                    $setcode = trim($sc);
                    $number = '';
                elseif($insideBrackets && $no !== '' && !$closingBracket):
                    $setcode = trim($sc);
                    $number = trim($no).'%';
                elseif($setClosed && $no !== '' && $closingBracket):
                    $setcode = trim($sc);
                    $number = trim($no);
                endif;
                $typed = trim($str);
                $searchString = '%'.trim($str).'%';
                // Here $typed is the text typed
            else:
                // No brackets in this case
                $typed = trim($r);
                $searchString = '%'.trim($r).'%';
                $setcode = ''; 
                $number = '';
            endif;
            $msg->logMessage('[DEBUG]', "Typed: '$typed'; Search: '$searchString'; Setcode: '$setcode'; Number: '$number' ");
            
            if(isset($setcode)):
                if(isset($number)):
                    $teststring = trim(trim($setcode)." ".trim($number));
                else:
                    $teststring = trim($setcode);
                endif;
                $msg->logMessage('[DEBUG]', "Testing '$teststring' against Brackets list");
                if(isset($teststring) && in_array_case_insensitive($teststring, $bracketsInNames)):
                    $msg->logMessage('[DEBUG]', "Bracket contents match a card with brackets in name, resetting name, set to match");
                    $searchString = $typed = $typed." (".$teststring.")";
                    $setcode = $number = '';
                endif;
            endif;
            
            $msg->logMessage('[DEBUG]',"Search string (q) is '$searchString', match string (t) is '$typed', setcode is '$setcode', number is '$number'");
            // Header search only searches within primary_card set, not additional languages
            $stmt = $db->prepare("SELECT id, setcode, name, printed_name, flavor_name, f1_name, f1_printed_name, f1_flavor_name, f2_name, f2_printed_name, f2_flavor_name, release_date
                          FROM cards_scry
                          WHERE
                          (printed_name LIKE ? 
                          OR flavor_name LIKE ? 
                          OR name LIKE ? 
                          OR f1_printed_name LIKE ? 
                          OR f1_flavor_name LIKE ? 
                          OR f1_name LIKE ?
                          OR f2_printed_name LIKE ? 
                          OR f2_flavor_name LIKE ? 
                          OR f2_name LIKE ?)
                          AND
                          (setcode LIKE ? OR ? = '')
                          AND
                          (number_import LIKE ? or ? = '')
                          AND 
                          (primary_card = 1) 
                          ORDER BY release_date DESC, name ASC LIMIT 20");
            $stmt->bind_param("sssssssssssss", $searchString, $searchString, $searchString, $searchString, $searchString, $searchString, $searchString, $searchString, $searchString, $setcode, $setcode, $number, $number);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $setcode, $name, $printed_name, $flavor_name, $f1_name, $f1_printed_name, $f1_flavor_name, $f2_name, $f2_printed_name, $f2_flavor_name, $release_date);

            if ($stmt->error):
                trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $stmt->error, E_USER_ERROR);
            else: ?>
                <table class='ajaxshow'> <?php 
                    while($row = $stmt->fetch()):
                        if($printed_name !== null AND strpos(strtolower($printed_name),strtolower($typed)) !== false):
                            $name = $printed_name;
                        elseif($flavor_name !== null AND strpos(strtolower($flavor_name),strtolower($typed)) !== false):
                            $name = $flavor_name;
                        elseif($f1_name !== null AND strpos(strtolower($f1_name),strtolower($typed)) !== false):
                            $name = $f1_name;
                        elseif($f1_printed_name !== null AND strpos(strtolower($f1_printed_name),strtolower($typed)) !== false):
                            $name = $f1_printed_name;
                        elseif($f1_flavor_name !== null AND strpos(strtolower($f1_flavor_name),strtolower($typed)) !== false):
                            $name = $f1_flavor_name;
                        elseif($f2_name !== null AND strpos(strtolower($f2_name),strtolower($typed)) !== false):
                            $name = $f2_name;
                        elseif($f2_printed_name !== null AND strpos(strtolower($f2_printed_name),strtolower($typed)) !== false):
                            $name = $f2_printed_name;
                        elseif($f2_flavor_name !== null AND strpos(strtolower($f2_flavor_name),strtolower($typed)) !== false):
                            $name = $f2_flavor_name;
                        endif;
                        $displaysetcode = strtoupper($setcode);
                        $query = "SELECT id, number_import FROM cards_scry WHERE id LIKE ? LIMIT 1";
                        $params = [$id];
                        $result = $db->execute_query($query, $params);
                        if($result === false):
                            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                        else:
                            $row = $result->fetch_assoc();
                            if ($row):
                                $ajaxid = $row['id'];
                                $ajaxnumber = $row['number_import'];
                                $b_name = '<strong>'.$typed.'</strong>';
                                $final_name = str_ireplace($typed, $b_name, $name);
                                ?>
                                <tr>
                                    <td title='<?php echo "$displaysetcode - $name" ?>' class="name"><?php echo "<a href='carddetail.php?id=$ajaxid'>$displaysetcode - $final_name</a></td>"; ?>
                                </tr>
                                <?php
                            endif;
                        endif;
                    endwhile; ?>
                </table> <?php
            endif;
        endif;
    endif;
else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from index.php($expectedReferringSite,$referringPage");
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>