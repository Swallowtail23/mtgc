<?php

/*
Version:     1.23
Date:        10/01/26
Name:        ajaxdeckcard.php
Purpose:     AJAX actions for deck card updates.
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
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
require_once 'ajaxdeckfragments_lib.php';
$msg = new \MTG\Core\Message($logfile);

$response = [
    'success' => false,
    'error' => ''
];

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = \MTG\Auth\SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxdeckcard.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $response['error'] = 'Invalid request token';
    else :
        $response['error'] = 'Access forbidden';
    endif;
    http_response_code(403);
    returnResponse($response);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['error'] = 'User not logged in';
    returnResponse($response);
endif;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') :
    $response['error'] = 'Invalid request method';
    returnResponse($response);
endif;

$csrfToken = $_POST['csrf_token'] ?? '';
if (!\MTG\Auth\SessionManager::validateCsrfToken($csrfToken)) :
    $response['error'] = 'Invalid request token';
    returnResponse($response);
endif;

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$deckNumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_NUMBER_INT);
$cardId = filter_input(INPUT_POST, 'cardid', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($action === null || $deckNumber === null || $cardId === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

$msg->logMessage('[DEBUG]', "Deck action '$action' for deck $deckNumber and card $cardId");

$sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
$userArray = $sessionManager->getUserInfo();
$user = $userArray['usernumber'];
$userEmail = $_SESSION['useremail'];
$rate = $userArray['rate'] ?? null;
$targetCurrency = $userArray['currency'] ?? null;
$mytable = $userArray['table'] ?? '';
if ($mytable === '') :
    $msg->logMessage('[ERROR]', "Missing collection table for user $user");
    $response['error'] = 'Missing collection table';
    returnResponse($response);
endif;

$deckManager = new \MTG\Cards\DeckManager(
    $db,
    $logfile,
    $userEmail,
    $serverEmail,
    $importLinestoIgnore,
    $nonPreferredSetCodes
);

$deckOwnerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdeckcard.php');
if ($deckOwnerCheck === false) :
    $msg->logMessage('[ERROR]', "Deck ownership check failed for deck $deckNumber");
    $response['error'] = 'Deck ownership check failed';
    returnResponse($response);
endif;

if ($action === 'plusmain') :
    $result = $deckManager->addDeckCard($deckNumber, $cardId, "main", "1");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'minusmain') :
    $result = $deckManager->subtractDeckCard($deckNumber, $cardId, "main", "1");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'deletemain') :
    $result = $deckManager->subtractDeckCard($deckNumber, $cardId, "main", "all");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'maintoside') :
    if ($deckManager->subtractDeckCard($deckNumber, $cardId, 'main', '1') != "-error") :
        $deckManager->addDeckCard($deckNumber, $cardId, "side", "1");
    endif;
    $result = 'maintoside';
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'plusside') :
    $result = $deckManager->addDeckCard($deckNumber, $cardId, "side", "1");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'minusside') :
    $result = $deckManager->subtractDeckCard($deckNumber, $cardId, "side", "1");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'deleteside') :
    $result = $deckManager->subtractDeckCard($deckNumber, $cardId, "side", "all");
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'sidetomain') :
    if ($deckManager->subtractDeckCard($deckNumber, $cardId, 'side', '1') != "-error") :
        $deckManager->addDeckCard($deckNumber, $cardId, "main", "1");
    endif;
    $result = 'sidetomain';
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'commander_add') :
    $result = $deckManager->addCommander($deckNumber, $cardId);
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'partner_add') :
    $result = $deckManager->addPartner($deckNumber, $cardId);
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
elseif ($action === 'commander_remove') :
    $result = $deckManager->delCommander($deckNumber, $cardId);
    $msg->logMessage('[DEBUG]', "Deck action result: $result");
else :
    $response['error'] = 'Unsupported action';
    returnResponse($response);
endif;

$qtyQuery = "SELECT cardqty, sideqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? LIMIT 1";
$qtyResult = $db->execute_query($qtyQuery, [$deckNumber, $cardId]);
$cardqty = 0;
$sideqty = 0;
if ($qtyResult !== false && $qtyResult->num_rows > 0) :
    $qtyRow = $qtyResult->fetch_assoc();
    $cardqty = (int) ($qtyRow['cardqty'] ?? 0);
    $sideqty = (int) ($qtyRow['sideqty'] ?? 0);
endif;

$response['success'] = true;
$response['status'] = $result;
$response['cardqty'] = $cardqty;
$response['sideqty'] = $sideqty;
$response['deck_version'] = getDeckVersion($db, $deckNumber);

if ($action === 'maintoside' && $sideqty > 0) :
    $decktype = '';
    $decktypeQuery = "SELECT type FROM decks WHERE decknumber = ? LIMIT 1";
    $decktypeResult = $db->execute_query($decktypeQuery, [$deckNumber]);
    if ($decktypeResult !== false && $decktypeResult->num_rows > 0) :
        $decktypeRow = $decktypeResult->fetch_assoc();
        $decktype = $decktypeRow['type'] ?? '';
    endif;
    $isCommanderDeck = in_array($decktype, $commander_decktypes);
    $msg->logMessage('[DEBUG]', "Deck type for sideboard insert: $decktype");
    $red_font_tag = "style='color: OrangeRed; font-weight: bold'";
    $firebrick_font_tag = "style='color: FireBrick; font-weight: bold'";
    $illegal_tag = $red_font_tag;
    $wrong_colour_tag = $firebrick_font_tag;
    $deck_legality_list = '';
    $db_field = $decktype !== '' ? $deckManager->cardLegalDBField($decktype) : '';
    if ($db_field !== '') :
        $deck_legality_list = $deckManager->deckLegalList($deckNumber, $decktype, $db_field);
    endif;
    $cdr_colours_raw = '';
    if ($isCommanderDeck) :
        $commanderQuery = "SELECT cards_scry.color_identity
            FROM deckcards
            LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
            WHERE decknumber = ? AND deckcards.commander IS NOT NULL AND deckcards.commander != 0";
        $commanderResult = $db->execute_query($commanderQuery, [$deckNumber]);
        $commanderColours = [];
        if ($commanderResult !== false && $commanderResult->num_rows > 0) :
            while ($commanderRow = $commanderResult->fetch_assoc()) :
                $commanderColours[] = $commanderRow['color_identity'];
            endwhile;
        endif;
        if (count($commanderColours) > 0) :
            $cdr_colours_raw = '["' . count_chars(
                str_replace(array('"', '[', ']', ',', ' '), '', implode(",", $commanderColours)),
                3
            ) . '"]';
        endif;
    endif;
    $detailQuery = "SELECT cards_scry.id AS cardsid,
            cards_scry.name,
            cards_scry.flavor_name,
            cards_scry.rarity,
            cards_scry.setcode,
            cards_scry.number_import,
            cards_scry.layout,
            cards_scry.type,
            cards_scry.cmc,
            cards_scry.f1_type,
            cards_scry.f1_cmc,
            cards_scry.ability,
            cards_scry.f1_ability,
            cards_scry.f2_ability,
            cards_scry.color_identity
        FROM cards_scry
        WHERE cards_scry.id = ? LIMIT 1";
    $detailResult = $db->execute_query($detailQuery, [$cardId]);
    if ($detailResult !== false && $detailResult->num_rows > 0) :
        $detailRow = $detailResult->fetch_assoc();
        $cardname = $detailRow['name'];
        if (isset($detailRow['flavor_name']) and !empty($detailRow['flavor_name'])) :
            $cardname = $detailRow['flavor_name'];
        endif;
        $rarity = $detailRow['rarity'];
        $cardset = strtolower($detailRow['setcode']);
        $cardnumber = $detailRow['number_import'];
        $layout = $detailRow['layout'];
        $card_type = $detailRow['type'];
        if ($card_type === null and isset($detailRow['f1_type'])) :
            $card_type = $detailRow['f1_type'];
        endif;
        $cardref = str_replace('.', '-', $detailRow['cardsid']);
        $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
        $imageFunction = $imageManager->getImage(
            $cardset,
            $cardId,
            $imgLocation,
            $layout,
            $twoCardDetailSections,
            false
        );
        if ($imageFunction['front'] == 'error') :
            $imageUrl = '/images/back.jpg';
        else :
            $imageUrl = $imageFunction['front'];
        endif;
        if (
            $deck_legality_list != ''
            and (
                (strpos($card_type, 'Plane') === false
                || strpos($card_type, 'Planeswalker') !== false)
            )
            and strpos($card_type, 'Phenomenon') === false
        ) :
            $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
            if ($index !== false) :
                $card_legal = $deck_legality_list[$index]['legality'];
                if ($card_legal === 'legal' or $card_legal === null) :
                    $illegal_tag = '';
                endif;
            else :
                $illegal_tag = '';
            endif;
        else :
            $illegal_tag = '';
        endif;
        if (
            $isCommanderDeck
            and $illegal_tag == ''
            and (
                (strpos($card_type, 'Plane') === false
                || strpos($card_type, 'Planeswalker') !== false)
            )
            and (strpos($card_type, 'Phenomenon') === false)
        ) :
            $colour_id = count_chars(
                str_replace(array('"', '[', ']', ',', ' '), '', $detailRow['color_identity']),
                3
            );
            $colour_id_array = str_split($colour_id);
            $card_colour_mismatch = '';
            foreach ($colour_id_array as $value) :
                if (strpos($cdr_colours_raw, $value) == false) :
                    $card_colour_mismatch = true;
                endif;
            endforeach;
            if ($card_colour_mismatch == '' or $colour_id == '') :
                $wrong_colour_tag = '';
            else :
                $illegal_tag = $wrong_colour_tag;
            endif;
        endif;
        if (
            in_array($layout, $image90rotate)
            or (isset($detailRow['f1_type']) and in_array($detailRow['f1_type'], $image90rotate))
        ) :
            $hoverclass = 'deckcardimgdiv splitfloat';
        else :
            $hoverclass = 'deckcardimgdiv';
        endif;
        $maxCopies = $deckManager->mtgCardCopyLimit(
            $card_type,
            $detailRow['ability'] ?? null,
            $detailRow['f1_ability'] ?? null,
            $detailRow['f2_ability'] ?? null,
            $decktype
        );
        $canAddMore = true;
        if ($maxCopies !== null) :
            $totalQuery = "SELECT SUM(IFNULL(deckcards.cardqty, 0) + IFNULL(deckcards.sideqty, 0)) AS totalqty
                FROM deckcards
                LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                WHERE deckcards.decknumber = ? AND cards_scry.name = ? LIMIT 1";
            $totalResult = $db->execute_query($totalQuery, [$deckNumber, $detailRow['name']]);
            if ($totalResult !== false && $totalResult->num_rows > 0) :
                $totalRow = $totalResult->fetch_assoc();
                $currentCopies = (int) ($totalRow['totalqty'] ?? 0);
                if ($currentCopies >= $maxCopies) :
                    $canAddMore = false;
                endif;
            endif;
        endif;
        $addStyle = $canAddMore ? '' : ' display: none;';

        $sideRowHtml = "<tr class='deckrow' data-section='sideboard' data-cardid='{$cardId}' "
            . "data-cardref='{$cardref}' data-qty='{$sideqty}'>"
            . "<td class=\"deckcardname hoverTD\">"
            . "<a class='taphover' {$illegal_tag} id='listside-{$cardref}-taphover' "
            . "href='carddetail.php?id={$cardId}'>"
            . "{$cardname} ({$cardset} <i class='ss ss-{$cardset} ss-{$rarity} ss-grad ss-fw'></i>)"
            . "</a></td>";
        if ($isCommanderDeck) :
            $sideRowHtml .= "<td class='deckcardlistcenter noprint'></td>";
        endif;
        $sideRowHtml .= "<td class='deckcardlistcenter noprint'>"
            . "<span onmouseover=\"\" title=\"Delete\" style=\"cursor: pointer;\" "
            . "class='material-symbols-outlined js-deleteside' data-cardid=\"{$cardId}\" "
            . "data-cardref=\"{$cardref}\">delete_forever</span></td>"
            . "<td class='deckcardlistcenter noprint'>"
            . "<span onmouseover=\"\" title=\"Move to main deck\" style=\"cursor: pointer;\" "
            . "class='material-symbols-outlined js-sidetomain' data-cardid=\"{$cardId}\" "
            . "data-cardref=\"{$cardref}\">arrow_upward</span></td>";
        if ($isCommanderDeck !== true) :
            $sideRowHtml .= "<td class='deckcardlistright noprint'>"
                . "<span onmouseover=\"\" title=\"Remove one\" style=\"cursor: pointer;\" "
                . "class='material-symbols-outlined js-minusside' data-cardid=\"{$cardId}\" "
                . "data-cardref=\"{$cardref}\">remove</span></td>"
                . "<td class='deckcardlistcenter js-qty-side' id='qty-side-{$cardref}'>{$sideqty}</td>"
                . "<td class='deckcardlistleft noprint'>"
                . "<span onmouseover=\"\" title=\"Add one\" style=\"cursor: pointer;{$addStyle}\" "
                . "class='material-symbols-outlined js-plusside' data-cardid=\"{$cardId}\" "
                . "data-cardref=\"{$cardref}\" data-maxcopies=\"{$maxCopies}\">add</span></td>";
        endif;
        $sideRowHtml .= "</tr>";
        $sideHoverHtml = "<div class='{$hoverclass}' id='listside-{$cardref}'>"
            . "<a href='carddetail.php?id={$cardId}'>"
            . "<img alt='{$cardname}' class='deckcardimg' data-cardid=\"{$cardId}\" "
            . "data-front-src=\"{$imageUrl}\" src='{$imageUrl}'></a></div>";
        $response['side_row_html'] = $sideRowHtml;
        $response['side_hover_html'] = $sideHoverHtml;
        $response['cardref'] = $cardref;
    endif;
endif;

$requestedFragments = isset($_POST['fragments']) ? (array) $_POST['fragments'] : [];
if (count($requestedFragments) === 0) :
    $requestedFragments = deckdetailDefaultFragments();
endif;

$skip_deckdetail_actions = true;
include '../includes/deckdetail_data.php';
include '../includes/fragments/deckdetail_mana_data.php';
$msg->logMessage(
    '[DEBUG]',
    "Deck action fragments requested for deck $deckNumber: " . implode(', ', array_map('strval', $requestedFragments))
);
$response['fragments'] = deckdetailRenderFragments($requestedFragments);
if (isset($deck_version)) :
    $response['deck_version'] = (int) $deck_version;
    $response['version'] = (int) $deck_version;
endif;

returnResponse($response);

function getDeckVersion($db, $deckNumber)
{
    $versionQuery = "SELECT (UNIX_TIMESTAMP(deck_updated_at) * 1000000 + MICROSECOND(deck_updated_at)) AS deck_version
        FROM decks WHERE decknumber = ? LIMIT 1";
    $versionResult = $db->execute_query($versionQuery, [$deckNumber]);
    if ($versionResult !== false && $versionResult->num_rows > 0) :
        $versionRow = $versionResult->fetch_assoc();
        return (int) ($versionRow['deck_version'] ?? 0);
    endif;
    return 0;
}

function returnResponse($response)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    ajaxRespondJson($response, http_response_code());
}
