<?php

/*
Version:     7.66
Date:        27/03/26
Name:        ajaxsearch.php
Purpose:     PHP script to run ajax search from header
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:      -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;
use MTG\Core\Text\QuickSearchInputParser;
use MTG\Core\Text\QuickSearchService;
use MTG\Core\Text\SearchTextHelper;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

$rulesBracketsInNames = $gameRules->getArray('bracketsInNames');

// Content
$expectedReferringPages = [
    $myURL
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxsearch.php');
if ($ajaxValidation['valid'] === false) :
    $msg->logMessage('[ERROR]', "Not called from valid page");
    AjaxResponse::text('Access forbidden', 403);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    //
    if ($_POST) :
        $r = $_POST['search'];
        $rtrim = trim($r, " \t\n\r\0\x0B");
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
        $r = preg_replace($regex, ' ', $rtrim);
        $r = filter_var($r, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        $msg->logMessage('[DEBUG]', "Ajax search after URL removal and filtering is '$r'");
        $parsedSearch = QuickSearchInputParser::parse($r, $rulesBracketsInNames);
        $typed = $parsedSearch['typed'];
        $searchString = $parsedSearch['search_string'];
        $setcode = $parsedSearch['setcode'];
        $number = $parsedSearch['number'];
        $msg->logMessage(
            '[DEBUG]',
            "Typed: '$typed'; Search: '$searchString'; Setcode: '$setcode'; Number: '$number' "
        );

        $quickSearchService = new QuickSearchService($db, $msg);
        $searchResult = $quickSearchService->search($typed, $searchString, $setcode, $number);
        $searchRows = $searchResult['rows'];
        $usedFallbackSearch = $searchResult['used_fallback'];

        if ($db->error) :
            throw new Exception(
                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $db->error
            );
        else : ?>
                <table class='ajaxshow'> <?php
                foreach ($searchRows as $row) :
                    $id = $row['id'];
                    $setcode = $row['setcode'];
                    $ajaxnumber = $row['number_import'];
                    $lang = $row['lang'];
                    $name = $row['matched_name'];
                    $matchPosition = (int) $row['matched_position'];
                    $displayName = SearchTextHelper::appendLanguageCodeSuffix(
                        $name,
                        $lang !== null ? (string) $lang : null,
                        $usedFallbackSearch
                    );
                    $displaysetcode = strtoupper($setcode);
                    $ajaxid = $id;
                    $final_name = SearchTextHelper::formatQuickSearchDisplayLabel(
                        $name,
                        $matchPosition,
                        mb_strlen($typed, 'UTF-8'),
                        $lang !== null ? (string) $lang : null,
                        $usedFallbackSearch
                    );
                    ?>
                            <tr>
                                <td title='<?php echo "$displaysetcode - $displayName" ?>' class="name">
                                    <?php
                                    echo "<a href='carddetail.php?id=$ajaxid'>$displaysetcode - "
                                        . "$final_name</a></td>";
                                    ?>
                                </td>
                            </tr>
                            <?php
                endforeach; ?>
                </table> <?php
        endif;
    endif;
endif;
?>
