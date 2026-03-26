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

        $matchedNameFields = [
            'printed_name',
            'flavor_name',
            'f1_name',
            'f1_printed_name',
            'f1_flavor_name',
            'f2_name',
            'f2_printed_name',
            'f2_flavor_name'
        ];
        $searchableFields = [
            'printed_name',
            'flavor_name',
            'name',
            'f1_printed_name',
            'f1_flavor_name',
            'f1_name',
            'f2_printed_name',
            'f2_flavor_name',
            'f2_name'
        ];
        $matchedNameWhenClauses = [];
        $matchedNameParams = [];
        foreach ($matchedNameFields as $fieldName) :
            $matchedNameWhenClauses[] = "WHEN {$fieldName} LIKE ? THEN {$fieldName}";
            $matchedNameParams[] = $searchString;
        endforeach;

        $matchedPositionWhenClauses = [];
        $matchedPositionParams = [];
        foreach ($searchableFields as $fieldName) :
            $matchedPositionWhenClauses[] = "WHEN {$fieldName} LIKE ? THEN LOCATE(?, {$fieldName})";
            $matchedPositionParams[] = $searchString;
            $matchedPositionParams[] = $typed;
        endforeach;

        $whereLikeClauses = [];
        $whereLikeParams = [];
        foreach ($searchableFields as $fieldName) :
            $whereLikeClauses[] = "{$fieldName} LIKE ?";
            $whereLikeParams[] = $searchString;
        endforeach;
        $filterParams = array_merge(
            $whereLikeParams,
            [
                $setcode,
                $setcode,
                $number,
                $number
            ]
        );
        $searchParams = array_merge($matchedNameParams, $matchedPositionParams, $filterParams);
        $searchQueryBase = "SELECT
                id,
                setcode,
                number_import,
                lang,
                CASE
                    " . implode("
                    ", $matchedNameWhenClauses) . "
                    ELSE name
                END AS matched_name,
                CASE
                    " . implode("
                    ", $matchedPositionWhenClauses) . "
                    ELSE 0
                END AS matched_position,
                release_date
            FROM cards_scry
            WHERE
                (
                    " . implode("
                    OR ", $whereLikeClauses) . "
                )
                AND (setcode LIKE ? OR ? = '')
                AND (number_import LIKE ? OR ? = '')";
        $searchQueryOrder = "
            ORDER BY release_date DESC, name ASC
            LIMIT 20";
        $runQuickSearch = function (bool $primaryOnly) use (
            $db,
            $msg,
            $searchQueryBase,
            $searchQueryOrder,
            $searchParams
        ) {
            $query = $searchQueryBase;
            if ($primaryOnly) :
                $query .= "
                AND (primary_card = 1)";
            endif;
            $query .= $searchQueryOrder;
            $msg->logMessage(
                '[DEBUG]',
                'Ajax header search running with '
                . ($primaryOnly ? 'primary-card-only filter' : 'all-language fallback')
            );
            $result = $db->execute_query($query, $searchParams);
            if ($result === false) :
                throw new Exception(
                    "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $db->error
                );
            endif;
            return $result;
        };

        $result = $runQuickSearch(true);
        $searchRows = $result->fetch_all(MYSQLI_ASSOC);
        $usedFallbackSearch = false;

        if (empty($searchRows) && mb_strlen($typed) >= 3) :
            $usedFallbackSearch = true;
            $msg->logMessage(
                '[DEBUG]',
                "Ajax header search found no primary-card matches for '$typed'; retrying without primary_card filter"
            );
            $result = $runQuickSearch(false);
            $searchRows = $result->fetch_all(MYSQLI_ASSOC);
        endif;

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
