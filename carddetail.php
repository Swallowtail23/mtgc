<?php

/*
Version:     22.59
Date:        26/02/26
Name:        carddetail.php
Purpose:     Card detail page
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;
use MTG\Cards\DeckManager;
use MTG\Cards\ImageManager;
use MTG\Core\Http\UrlHelper;
use MTG\Core\Text\TextHelper;
use MTG\Core\Validation;

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');
$sessionUser                = $ctx->sessionUser();

$siteTitle                  = (string) $appConfig->general('title', '');
$imgLocation                = (string) $appConfig->general('imageBaseDir', '');
$tier                       = (string) $appConfig->general('tier', 'prod');
$disqus                     = (int) $appConfig->comments('disqusEnabled', false);
$disqusDev                  = (string) $appConfig->comments('disqusDevUrl', '');
$disqusProd                 = (string) $appConfig->comments('disqusProdUrl', '');
$copyright                  = (string) $appConfig->general('copyright', '');

$user                       = $sessionUser->id();
$admin                      = $sessionUser->adminLevel();
$mytable                    = $sessionUser->table();
$userEmail                  = $sessionUser->email();
$fx                         = $sessionUser->fxEnabled();
$fxPending                  = $sessionUser->fxPending();
$fxMissing                  = $sessionUser->fxMissing();
$targetCurrency             = $sessionUser->currency();
$rate                       = $sessionUser->rate();
$groupInOut                 = $sessionUser->groupInOut();
$groupId                    = $sessionUser->groupId();

$rulesImage90Rotate         = $gameRules->getArray('image90rotate');
$rulesLayoutsDouble         = $gameRules->getArray('layouts_double');
$rulesPromosToShow          = $gameRules->getArray('promos_to_show');
$rulesTokenLayouts          = $gameRules->getArray('token_layouts');
$rulesTwoCardDetailSections = $gameRules->getArray('twoCardDetailSections');

// Content
// Is admin running the page
$msg->logMessage('[DEBUG]', "Admin is $admin");

// Enable / disable deck functionality
$decks_on = 1;

// Pass data to this form by e.g. ?id=123456
// GET is used from results page, POST is used for database update query.
if (isset($_GET["id"])) :
    $cardId = Validation::validUUID($_GET["id"], $appConfig);
elseif (isset($_POST["id"])) :
    $cardId = Validation::validUUID($_POST["id"], $appConfig);
endif;

$decktoaddto = filter_input(INPUT_GET, 'decktoaddto', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$newdeckname = filter_input(INPUT_GET, 'newdeckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
if (filter_input(INPUT_GET, 'deckqty', FILTER_SANITIZE_NUMBER_INT) == '') :
    $deckqty = 1;
else :
    $deckqty = filter_input(INPUT_GET, 'deckqty', FILTER_SANITIZE_NUMBER_INT);
endif;

$refreshimage = '';

$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - card details</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo $cssver?>.css?v=<?php echo $serviceWorkerVersion; ?>"
    >
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <link href="//cdn.jsdelivr.net/npm/mana-font@latest/css/mana.min.css" rel="stylesheet" type="text/css" />
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script type="text/javascript">
        window.mtgImageCacheName = 'mtg-images-<?php echo $serviceWorkerVersion; ?>';
    </script>
    <script src="/js/asyncImageRefresh.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script src="/js/ajaxUpdate.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body class="body">
<?php
include_once APP_ROOT . '/includes/analyticstracking.php';
// Start building the page here, so errors show in the website template
// Includes first - menu and header
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php'; //mobile menu
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons"
      rel="stylesheet">
<div id="page">
    <div id="carddetail"> <?php
    if ($cardId === false) :
        echo "<h2 class='h2pad'>Invalid card UUID</h2>";
        exit;
    endif; ?>
        <div id="printtitle" class="headername">
            <img src="images/white_m.png"><?php echo $siteTitleEsc;?>
        </div>
    <?php
    // Does the user have a collection table?
    $tableExistsQuery = "SHOW TABLES LIKE '$mytable'";
    $msg->logMessage('[DEBUG]', "Checking if user has a collection table...");

    $result = $db->query($tableExistsQuery);
    if ($result->num_rows == 0) :
        $msg->logMessage('[NOTICE]', "No existing collection table...");
        $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
        $msg->logMessage('[DEBUG]', "Copying collection template...: $query2");

        if ($db->query($query2) === true) :
            $msg->logMessage('[NOTICE]', "Collection template copy successful");
        else :
            $msg->logMessage('[NOTICE]', "Collection template copy failed: " . $db->error);
        endif;
    else :
        $msg->logMessage('[DEBUG]', "Collection table exists");
    endif;

    $scryfallresult = array();
    $msg->logMessage('[DEBUG]', "Skipping synchronous Scryfall price refresh - async update will run after load");
    $msg->logMessage('[DEBUG]', "Is card ID provided, does card exist? If yes load card details");
    if (isset($_GET["id"]) or isset($_POST["id"])) :
        $searchqry =
               "SELECT
                    cards_scry.id as cs_id,
                    oracle_id,
                    tcgplayer_id,
                    scryfalljson.tcg_buy_uri,
                    multiverse,
                    multiverse2,
                    name,
                    printed_name,
                    flavor_name,
                    lang,
                    primary_card,
                    release_date,
                    set_name as cs_setname,
                    setcode as cs_setcode,
                    set_id as cs_set_id,
                    game_types,
                    finishes,
                    promo_types,
                    type,
                    power,
                    toughness,
                    loyalty,
                    manacost,
                    cmc,
                    artist,
                    flavor,
                    color_identity,
                    generatedmana,
                    number,
                    number_import,
                    layout,
                    rarity,
                    ability,
                    keywords,
                    f1_name,
                    f1_manacost,
                    f1_type,
                    f1_ability,
                    f1_artist,
                    f1_flavor,
                    f1_power,
                    f1_toughness,
                    f1_loyalty,
                    f1_cmc,
                    f1_printed_name,
                    f1_flavor_name,
                    f2_name,
                    f2_manacost,
                    f2_type,
                    f2_ability,
                    f2_artist,
                    f2_flavor,
                    f2_power,
                    f2_toughness,
                    f2_loyalty,
                    f2_cmc,
                    f2_printed_name,
                    f2_flavor_name,
                    p1_id,
                    p1_component,
                    p1_name,
                    p1_type_line,
                    p1_uri,
                    p2_id,
                    p2_component,
                    p2_name,
                    p2_type_line,
                    p2_uri,
                    p3_id,
                    p3_component,
                    p3_name,
                    p3_type_line,
                    p3_uri,
                    p4_id,
                    p4_component,
                    p4_name,
                    p4_type_line,
                    p4_uri,
                    p5_id,
                    p5_component,
                    p5_name,
                    p5_type_line,
                    p5_uri,
                    p6_id,
                    p6_component,
                    p6_name,
                    p6_type_line,
                    p6_uri,
                    p7_id,
                    p7_component,
                    p7_name,
                    p7_type_line,
                    p7_uri,
                    reserved,
                    cards_scry.foil as cs_foil,
                    nonfoil as cs_normal,
                    oversized,
                    gatherer_uri,
                    image_uri,
                    api_uri,
                    scryfall_uri,
                    legalityblock,
                    legalitystandard,
                    legalitymodern,
                    legalitylegacy,
                    legalityvintage,
                    legalitypioneer,
                    legalityalchemy,
                    legalityhistoric,
                    legalitycommander,
                    updatetime,
                    price,
                    price_foil,
                    price_etched,
                    normal,
                    $mytable.foil,
                    $mytable.etched,
                    notes
                FROM cards_scry
                LEFT JOIN scryfalljson ON cards_scry.id = scryfalljson.id
                LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id
                WHERE cards_scry.id = ?
                LIMIT 1";
        $params = [$cardId];

        if ($result = $db->execute_query($searchqry, $params)) :
            $msg->logMessage('[DEBUG]', "SQL query succeeded");
        else :
                throw new Exception(
                    "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $db->error
                );
        endif;
            $qtyresults = $result->num_rows;
            // If the result has a card:
        if (!$qtyresults == 0) :
            $row = $result->fetch_array(MYSQLI_BOTH);
            $setcode = strtolower($row['cs_setcode']);
            $setcodeupper = strtoupper($setcode);
            $setname = stripslashes($row['cs_setname']);
            $cardname = stripslashes($row['name']);
            $id = $row['cs_id'];
            $card_lang = $row['lang'];
            $card_lang_uc = strtoupper($card_lang);
            $is_qya = ($card_lang === 'qya');
            $qya_name_open = $is_qya ? "<span class='font-alcarin-tengwar'>" : '';
            $qya_name_close = $is_qya ? '</span>' : '';
            $card_primary = $row['primary_card'];

            if ($row['f2_ability'] !== null) :
                $flipability = $row['f2_ability'];
            endif;
            if (strpos($row['game_types'], 'paper') == false) :
                $not_paper = true;
                $msg->logMessage('[DEBUG]', "Arena/Online only card");
            else :
                    $not_paper = false;
            endif;
                $thick = $serialised = false;
            if (isset($row['promo_types']) and $row['promo_types'] !== null) :
                $promo = json_decode($row['promo_types']);
                $msg->logMessage('[DEBUG]', "Card has a promo_type set: {$row['promo_types']}");
                $full_promo_text = '';
                foreach ($promo as $value) :
                    $promo_description = CardUtils::promoLookup($value, $rulesPromosToShow, $msg);
                    if ($promo_description !== 'skip') :
                        if ($full_promo_text === '') :
                            $full_promo_text = $full_promo_text . "$promo_description";
                        else :
                                $full_promo_text = $full_promo_text . ", $promo_description";
                        endif;
                    endif;
                endforeach;
            endif;
                $cardnumber = $row['number'];
            if (
                    ($row['p1_component'] === 'meld_result' and $row['p1_name'] === $row['name'])
                    or ($row['p2_component'] === 'meld_result' and $row['p2_name'] === $row['name'])
                    or ($row['p3_component'] === 'meld_result' and $row['p3_name'] === $row['name'])
                    or ($row['p4_component'] === 'meld_result' and $row['p4_name'] === $row['name'])
                    or ($row['p5_component'] === 'meld_result' and $row['p5_name'] === $row['name'])
                    or ($row['p6_component'] === 'meld_result' and $row['p6_name'] === $row['name'])
                    or ($row['p7_component'] === 'meld_result' and $row['p7_name'] === $row['name'])
            ) :
                $meld = 'meld_result';
            elseif (
                    $row['p1_component'] === 'meld_part'
                    or $row['p2_component'] === 'meld_part'
                    or $row['p3_component'] === 'meld_part'
                    or $row['p4_component'] === 'meld_part'
                    or $row['p5_component'] === 'meld_part'
                    or $row['p5_component'] === 'meld_part'
                    or $row['p6_component'] === 'meld_part'
                    or $row['p7_component'] === 'meld_part'
            ) :
                    $meld = 'meld_part';
            else :
                    $meld = '';
            endif;
            if (isset($row['price'])) :
                $price_log = $row['price'];
            else :
                    $price_log = null;
            endif;
            if (isset($row['price_foil'])) :
                $price_foil_log = $row['price_foil'];
            else :
                    $price_foil_log = null;
            endif;
            if (isset($row['price_etched'])) :
                $price_etched_log = $row['price_etched'];
            else :
                    $price_etched_log = null;
            endif;
                $msg->logMessage(
                    '[DEBUG]',
                    "Recorded price from database is: $price_log/$price_foil_log/$price_etched_log"
                );
                $tcg_buy_uri = $row['tcg_buy_uri'] ?? null;
                $msg->logMessage('[DEBUG]', "TCGPlayer buy URI from cached data: $tcg_buy_uri");
            if (isset($row['layout']) and $row['layout'] === "normal") :
                $scryfallimg = $row['image_uri'];
            else :
                    $scryfallimg = null;
            endif;

                $msg->logMessage('[DEBUG]', "Scryfall image location called by $userEmail: $scryfallimg");
                $imgname = $cardId . ".jpg";
                $imgname_2 = $cardId . "_b.jpg";
                $msg->logMessage(
                    '[DEBUG]',
                    "Call for getImage by $userEmail with $setcode,$id,$imgLocation, {$row['layout']}"
                );
                $imageManager = new ImageManager($db, $appConfig, $gameRules);
                $imageFunction = $imageManager->getImage(
                    $setcode,
                    $row['cs_id'],
                    $row['layout'],
                    false
                );
                $msg->logMessage('[DEBUG]', "getImage result: {$imageFunction['front']} / {$imageFunction['back']}");
            if ($imageFunction['front'] == 'error') :
                $imageUrl = '/cardimg/back.jpg';
            else :
                    $imageUrl = $imageFunction['front'];
            endif;
            if (!is_null($imageFunction['back'])) :
                if (
                    $imageFunction['back'] === ''
                    or $imageFunction['back'] === 'error'
                    or $imageFunction['back'] === 'empty'
                ) :
                    $imagebackurl = '/cardimg/back.jpg';
                else :
                        $imagebackurl = $imageFunction['back'];
                endif;
            endif;
                $settotal = 0;
                // If the current record has null fields set the variables to 0 so the update query works
            if (empty($row['normal'])) :
                $myqty = 0;
            else :
                    $myqty = $row['normal'];
            endif;
            if (empty($row['foil'])) :
                $myfoil = 0;
            else :
                    $myfoil = $row['foil'];
            endif;
            if (empty($row['etched'])) :
                $myetch = 0;
            else :
                    $myetch = $row['etched'];
            endif;
            if (empty($row['notes'])) :
                $notes = '';
            else :
                    $notes = $row['notes'];
            endif;

                //Process image change if it's been called by an admin.
            if (isset($_POST['import']) and $admin == 1) :
                $msg->logMessage('[NOTICE]', "Image upload called by $userEmail");
                if (is_uploaded_file($_FILES['filename']['tmp_name'])) :
                    $handle = fopen($_FILES['filename']['tmp_name'], "r");
                    $info = getimagesize($_FILES['filename']['tmp_name']);
                    if (($info === false) or ($info[2] !== IMAGETYPE_JPEG)) :
                        $msg->logMessage('[NOTICE]', "Image upload failed - not an image or not a JPG"); ?>
                        <div class="msg-new error-new"><span>Not a JPG image</span>
                            <br>
                                                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                        </div> <?php
                    else :
                            $upload_name = $imgLocation . strtolower($setcode) . "/" . $imgname;
                        if (!move_uploaded_file($_FILES['filename']['tmp_name'], $upload_name)) : ?>
                        <div class="msg-new error-new"><span>Image write failed</span>
                            <br>
                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                        </div> <?php
                        $msg->logMessage('[ERROR]', "Image upload for $cardId by $userEmail failed");
                        else :
                            // Image upload successful. Set variable to load card page 'fresh' at completion
                            // (see end of script)
                                $ctrlf5 = 1;
                                $msg->logMessage('[NOTICE]', "Image upload for $cardId by $userEmail ok");
                        endif;
                    endif;
                endif;
            endif;

                $setcode = htmlentities($setcode, ENT_QUOTES, "UTF-8");
                $setname = htmlentities($setname, ENT_QUOTES, "UTF-8");
                $namehtml = $row['name'];
                $row['name'] = htmlentities($row['name'], ENT_QUOTES, "UTF-8");
                $row['number'] = htmlentities($row['number'], ENT_QUOTES, "UTF-8");
                $row['type'] = (isset($row['type'])) ? htmlentities($row['type'], ENT_QUOTES, "UTF-8") : '';
                $row['manacost'] = (isset($row['manacost'])) ? htmlentities($row['manacost'], ENT_QUOTES, "UTF-8") : '';
                $row['cmc'] = (isset($row['cmc'])) ? htmlentities($row['cmc'], ENT_QUOTES, "UTF-8") : '';
                $row['power'] = (isset($row['power'])) ? htmlentities($row['power'], ENT_QUOTES, "UTF-8") : '';
                $row['toughness'] = isset($row['toughness'])
                    ? htmlentities($row['toughness'], ENT_QUOTES, "UTF-8")
                    : '';
                $row['loyalty'] = (isset($row['loyalty'])) ? htmlentities($row['loyalty'], ENT_QUOTES, "UTF-8") : '';
                $row['artist'] = (isset($row['artist'])) ? htmlentities($row['artist'], ENT_QUOTES, "UTF-8") : '';
                $card_normal = (isset($row['cs_normal'])) ? $row['cs_normal'] : '';
                $card_foil = (isset($row['cs_foil'])) ? $row['cs_foil'] : '';
                $myqty = (isset($myqty)) ? htmlentities($myqty, ENT_QUOTES, "UTF-8") : '';
                $myfoil = (isset($myfoil)) ? htmlentities($myfoil, ENT_QUOTES, "UTF-8") : '';
                $myetch = (isset($myetch)) ? htmlentities($myetch, ENT_QUOTES, "UTF-8") : '';

                //Set card types
            if (isset($row['finishes'])) :
                $finishes = json_decode($row['finishes'], true);
                $cardtypes = CardUtils::cardTypes($finishes);
            else :
                    $finishes = null;
                    $cardtypes = 'none';
            endif;
                $msg->logMessage('[DEBUG]', "Current card: {$row['cs_id']} is $cardtypes");
            ?>
                <div id="carddetailheader">
                    <table>
                        <tr>
                            <td class="h2pad" id='nameheading'>
                                <?php
                                if (isset($row['flavor_name']) and $row['flavor_name'] !== '') :
                                    echo "{$qya_name_open}{$row['flavor_name']}{$qya_name_close}"
                                        . " <i>({$row['name']})</i>";
                                elseif ($card_lang === 'ph') :
                                        echo $row['name'];
                                elseif ($row['printed_name'] != '' and $row['printed_name'] != $row['name']) :
                                        echo "{$qya_name_open}{$row['printed_name']}"
                                            . "{$qya_name_close} <i>({$row['name']})</i>";
                                else :
                                        echo $qya_name_open . $row['name'] . $qya_name_close;
                                endif;
                                $colourIdentity = CardUtils::colourIdentity($row['color_identity']);
                                if ($colourIdentity !== '') :
                                    echo ' ' . $colourIdentity;
                                endif;
                                ?>
                            </td>
                            <td id="carddetailset">
                                    <?php
                                    if ($card_primary === 1) :
                                        echo "<a href='index.php?complex=yes&amp;sortBy=auto&amp;set%5B%5D=$setcode'>"
                                        . "$setname</a>&nbsp;";
                                    else :
                                        echo "<a href='index.php?complex=yes&amp;sortBy=auto&amp;set%5B%5D=$setcode"
                                        . "&amp;lang=$card_lang'>$setname ($card_lang_uc)</a>&nbsp;";
                                    endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td  id="carddetailflavour" colspan="3">
                                    <?php
                                    if ($row['f1_flavor'] != '' and $row['f2_flavor'] != '') :
                                        $mainflavour = htmlentities($row['f1_flavor'], ENT_QUOTES, "UTF-8") . " // "
                                        . htmlentities($row['f2_flavor'], ENT_QUOTES, "UTF-8");
                                    elseif ($row['flavor'] != '' and $row['f2_flavor'] != '') :
                                        $mainflavour = htmlentities($row['flavor'], ENT_QUOTES, "UTF-8") . " // "
                                        . htmlentities($row['f2_flavor'], ENT_QUOTES, "UTF-8");
                                    elseif ($row['f1_flavor'] != '' and $row['f2_flavor'] == '') :
                                        $mainflavour = htmlentities($row['f1_flavor'], ENT_QUOTES, "UTF-8");
                                    elseif ($row['f2_flavor'] != '' and $row['f1_flavor'] == '') :
                                        $mainflavour = htmlentities($row['f2_flavor'], ENT_QUOTES, "UTF-8");
                                    elseif ($row['flavor'] != '') :
                                        $mainflavour = htmlentities($row['flavor'], ENT_QUOTES, "UTF-8");
                                    else :
                                        $mainflavour = null;
                                    endif;
                                    if ($mainflavour !== null) :
                                        echo $mainflavour;
                                    else :
                                        echo "&nbsp;";
                                    endif;
                                    ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div id="minicarddetailheader"> <?php
                    echo "<h2 class = 'h2pad'>";
                if (isset($row['flavor_name']) and $row['flavor_name'] !== '') :
                    echo "{$qya_name_open}{$row['flavor_name']}{$qya_name_close} <i>({$row['name']})</i>";
                elseif ($row['printed_name'] != '' and $row['printed_name'] != $row['name']) :
                        echo "{$qya_name_open}{$row['printed_name']}{$qya_name_close} <i>({$row['name']})</i>";
                else :
                        echo $qya_name_open . $row['name'] . $qya_name_close;
                endif;
                    echo "</h2>";
                if ($card_primary === 1) :
                    echo "<a href='index.php?complex=yes&amp;sortBy=auto&amp;set%5B%5D=$setcode'>$setname</a>&nbsp;";
                else :
                        echo "<a href='index.php?complex=yes&amp;sortBy=auto&amp;set%5B%5D=$setcode"
                            . "&amp;lang=$card_lang'>$setname ($card_lang_uc)</a>";
                endif; ?>
                </div>
                <div id="carddetailmain">
                    <div id="carddetailimage"><?php
                    if ($row['layout'] === 'flip') : ?>
                            <div style="cursor: pointer;" class="fliprotate js-rotate-img">
                                <span class='material-symbols-outlined refresh'>refresh</span>
                            </div> <?php
                    endif;
                        $img_id = 'cardimg';
                    if (in_array($row['layout'], $rulesTwoCardDetailSections)) :
                        $flipReady = (
                            strpos($imageUrl, 'cardimg') !== false and strpos($imagebackurl, 'cardimg') !== false
                            )
                            ? 1
                            : 0;
                        $frontImageEsc = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
                        $backImageEsc = htmlspecialchars($imagebackurl, ENT_QUOTES, 'UTF-8');
                        echo "<div style='cursor: pointer; display: none;' class='flipbuttondetail js-swap-image' "
                            . "data-cardid='{$row['cs_id']}' data-ready='{$flipReady}' "
                            . "data-image-front='{$frontImageEsc}' data-image-back='{$backImageEsc}' "
                            . "data-image-id='{$img_id}'>"
                            . "<span class='material-symbols-outlined refresh'>refresh</span></div>";
                    endif;
                        // Find the prev number card's ID
                        $msg->logMessage('[DEBUG]', "Finding previous and next cards");

                        // Using the current card's language and primary_card status, and the setcode, build the correct
                        // sort order and card list. Searches here should align with criteria.php AUTO ordering as
                        // default set order listings.

                    if ($row['cs_setcode'] === 'plst') :
                        // Unique sorting for The List cards, matching order as sorted under "Auto / The List"
                            $query = "SELECT id FROM cards_scry
                                    WHERE setcode = ?
                                    AND lang = ?
                                    AND primary_card = ?
                                    ORDER BY cards_scry.release_date DESC,
                                        (SELECT sets.release_date
                                            FROM sets
                                            WHERE sets.code = SUBSTRING(
                                                cards_scry.number_import,
                                                1,
                                                LOCATE('-', cards_scry.number_import) - 1
                                            )
                                        ) DESC,
                                        SUBSTRING(number_import, 1, LOCATE('-', number_import) - 1) ASC,
                                        CAST(
                                            SUBSTRING(number_import FROM LOCATE('-', number_import) + 1)
                                            AS UNSIGNED
                                        ) ASC,
                                        primary_card DESC, number ASC,
                                        COALESCE(flavor_name, name) ASC,
                                        id ASC ";
                    elseif ($row['cs_setcode'] === 'sld') :
                            // Unique sorting for Secret Lair cards, matching "Auto" ordering
                            $query = "SELECT id FROM cards_scry
                                    WHERE setcode = ?
                                    AND lang = ?
                                    AND primary_card = ?
                                    ORDER BY release_date DESC,
                                        number ASC,
                                        CAST(REGEXP_REPLACE(number_import, '[[:alpha:]]', '') AS UNSIGNED) ASC,
                                        number_import ASC,
                                        COALESCE(flavor_name, name) ASC,
                                        id ASC";
                    else :
                            $query = "SELECT id FROM cards_scry
                                    WHERE setcode = ?
                                    AND lang = ?
                                    AND primary_card = ?
                                    ORDER BY
                                        number ASC,
                                        release_date ASC,
                                        CAST(REGEXP_REPLACE(number_import, '[[:alpha:]]', '') AS UNSIGNED) ASC,
                                        number_import ASC,
                                        COALESCE(flavor_name, name) ASC,
                                        id ASC";
                    endif;
                        $stmt = $db->prepare($query);
                        $stmt->bind_param('ssi', $row['cs_setcode'], $card_lang, $card_primary);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $results = $result->fetch_all(MYSQLI_ASSOC);
                        $currentCardIndex = array_search($row['cs_id'], array_column($results, 'id'));
                        $msg->logMessage(
                            '[DEBUG]',
                            "Current card is index number $currentCardIndex in setcode {$row['cs_setcode']}"
                        );
            if ($currentCardIndex !== false) :
                $prevCardIndex = $currentCardIndex - 1;
                if (isset($results[$prevCardIndex])) :
                    // Retrieve the next card details
                    $prevCard = $results[$prevCardIndex];
                    $prevcardid = $prevCard['id'];
                    $msg->logMessage('[DEBUG]', "Previous card is $prevcardid");
                else :
                            $prevcardid = '';
                endif;
                    $nextCardIndex = $currentCardIndex + 1;
                if (isset($results[$nextCardIndex])) :
                    // Retrieve the next card details
                    $nextCard = $results[$nextCardIndex];
                    $nextcardid = $nextCard['id'];
                    $msg->logMessage('[DEBUG]', "Next card is $nextcardid");
                else :
                                $nextcardid = '';
                endif;
            else :
                            $prevcardid = '';
                            $nextcardid = '';
            endif;?>
                        <table>
                            <tr>
                                <td colspan="6">
                                    <?php
                                    $lookupid = htmlentities($row['cs_id'], ENT_QUOTES, "UTF-8");
                                    $imagelocation = $imageUrl;
                                        $msg->logMessage('[DEBUG]', "Image location is " . $imagelocation);
                                        // Set classes for hover image
                                    if (
                                            in_array($row['layout'], $rulesImage90Rotate)
                                            or in_array($row['f1_type'], $rulesImage90Rotate)
                                    ) :
                                        $hoverclass = 'imgfloat splitfloat';
                                    else :
                                            $hoverclass = 'imgfloat';
                                    endif; ?>
                                        <div class='<?php echo $hoverclass; ?>' id='image-<?php echo $row['cs_id'];?>'>
                                            <img
                                                class='mainimg'
                                                data-cardid="<?php echo $row['cs_id']; ?>"
                                                data-face="front"
                                                alt='<?php echo $imagelocation;?>'
                                                src='<?php echo $imagelocation;?>'
                                            >
                                        </div> <?php
                                        if (isset($row['multiverse'])) :
                                            $multiverse_id = $row['multiverse'];
                                            echo "<a href='https://gatherer.wizards.com/Pages/Card/Details.aspx?"
                                                . "multiverseid=" . $multiverse_id . "' target='_blank'>"
                                                . "<img alt='$lookupid' id='cardimg' class='mainimg' "
                                                . "data-cardid='{$row['cs_id']}' data-face='front' "
                                                . "src=$imagelocation>"
                                                . "</a>";
                                        elseif (isset($row['scryfall_uri'])) :
                                                echo "<a href='" . $row['scryfall_uri'] . "' target='_blank'>"
                                                . "<img alt='$lookupid' id='cardimg' class='mainimg' "
                                                . "data-cardid='{$row['cs_id']}' data-face='front' "
                                                . "src=$imagelocation>"
                                                . "</a>";
                                        else :
                                                echo "<a href='https://gatherer.wizards.com/' target='_blank'>"
                                                . "<img alt='$lookupid' id='cardimg' class='mainimg' "
                                                . "data-cardid='{$row['cs_id']}' data-face='front' "
                                                . "src=$imagelocation>"
                                                . "</a>";
                                        endif; ?>
                                </td>
                            </tr> <?php
                            if (!empty($prevcardid) && !empty($nextcardid)) : ?>
                                <tr>
                                    <td
                                        colspan="3"
                                        class="previousbutton js-submit-form"
                                        style="cursor: pointer;"
                                        data-submit-form="prev_card"
                                    >
                                        <?php if (!empty($prevcardid)) :
                                            $msg->logMessage('[DEBUG]', "Previous card ('$prevcardid')");
                                            $prevValue = htmlspecialchars(
                                                $prevcardid,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <form action="?" method="get" id="prev_card">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $prevValue; ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    title="Previous card in set"
                                                    class="material-symbols-outlined"
                                                    style="
                                                        background:none;
                                                        border:none;
                                                        cursor:pointer;
                                                        display:block;
                                                        text-align:center;
                                                        margin:0 auto;
                                                    ">
                                                    navigate_before
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td
                                        colspan="3"
                                        class="nextbutton js-submit-form"
                                        style="cursor: pointer;"
                                        data-submit-form="next_card"
                                    >
                                        <?php if (!empty($nextcardid)) :
                                            $msg->logMessage('[DEBUG]', "Next card ('$nextcardid')");
                                            $nextValue = htmlspecialchars(
                                                $nextcardid,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <form action="?" method="get" id="next_card">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $nextValue; ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    title="Next card in set"
                                                    class="material-symbols-outlined"
                                                    style="
                                                        background:none;
                                                        border:none;
                                                        cursor:pointer;
                                                        display:block;
                                                        text-align:center;
                                                        margin:0 auto;
                                                    ">
                                                    navigate_next
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr> <?php
                            elseif (!empty($nextcardid)) : ?>
                                    <?php
                                    $msg->logMessage('[DEBUG]', "Next card ('$nextcardid')");
                                    ?>
                                <tr>
                                    <td colspan="3" class="previousbutton" style="cursor: pointer;">&nbsp;</td>
                                    <td
                                        colspan="3"
                                        class="nextbutton js-submit-form"
                                        style="cursor: pointer;"
                                        data-submit-form="next_card"
                                    >
                                        <?php if (!empty($nextcardid)) :
                                            $nextValue = htmlspecialchars(
                                                $nextcardid,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <form action="?" method="get" id="next_card">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $nextValue; ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    title="Next card in set"
                                                    class="material-symbols-outlined"
                                                    style="
                                                        background:none;
                                                        border:none;
                                                        cursor:pointer;
                                                        display:block;
                                                        text-align:center;
                                                        margin:0 auto;
                                                    ">
                                                    navigate_next
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr> <?php
                            elseif (!empty($prevcardid)) : ?>
                                    <?php
                                    $msg->logMessage('[DEBUG]', "Previous card ('$prevcardid')");
                                    ?>
                                <tr>
                                    <td
                                        colspan="3"
                                        class="previousbutton js-submit-form"
                                        style="cursor: pointer;"
                                        data-submit-form="prev_card"
                                    >
                                        <?php if (!empty($prevcardid)) :
                                            $prevValue = htmlspecialchars(
                                                $prevcardid,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                            <form action="?" method="get" id="prev_card">
                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?php echo $prevValue; ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    title="Previous card in set"
                                                    class="material-symbols-outlined"
                                                    style="
                                                        background:none;
                                                        border:none;
                                                        cursor:pointer;
                                                        display:block;
                                                        text-align:center;
                                                        margin:0 auto;
                                                    ">
                                                    navigate_before
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td colspan="3" class="nextbutton" style="cursor: pointer;">&nbsp;</td>
                                </tr><?php
                            endif;

                                //If if's an admin, show controls to change/refresh the image(s) for the card.
                            if ($admin == 1) :
                                // Form to change the image
                                ?>
                                <tr>
                                    <td colspan='4'>
                                        <form
                                            id="imgreplace"
                                            action="?"
                                            method="GET"
                                            enctype="multipart/form-data"
                                        >
                                            <input
                                                type='hidden'
                                                name='setabbrv'
                                                value="<?php echo $row['cs_setcode']; ?>"
                                            >
                                            <input type='hidden' name='id' value="<?php echo $row[0]; ?>">
                                            <input type='hidden' name='number' value="<?php echo $row['number']; ?>">
                                            <table>
                                                <tr>
                                                    <td class='imgreplace'>
                                                        <label
                                                            class='importlabel'
                                                            style="cursor: pointer;"
                                                            id='imgpick'
                                                        >
                                                            <input
                                                                class='importlabel'
                                                                id='importfile'
                                                                type='file'
                                                                name='filename'
                                                            >
                                                            <span
                                                                title="New image"
                                                                onmouseover=""
                                                                style="
                                                                    cursor: pointer;
                                                                    display:block;
                                                                    text-align:center;
                                                                    margin:0 auto;
                                                                "
                                                                class='material-symbols-outlined card_detail'>
                                                                image
                                                            </span>
                                                        </label>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button
                                                            class='importlabel'
                                                            style="cursor: pointer;"
                                                            id='importsubmit'
                                                            type='submit'
                                                            name='import'
                                                            value='REPLACE'
                                                            disabled
                                                        >
                                                            <span
                                                                title="Replace image"
                                                                onmouseover=""
                                                                style="
                                                                    cursor: pointer;
                                                                    display:block;
                                                                    text-align:center;
                                                                    margin:0 auto;
                                                                "
                                                                class='material-symbols-outlined card_detail'>
                                                                done
                                                            </span>
                                                        </button>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button
                                                            class='importlabel'
                                                            style="cursor: pointer;"
                                                            id='refreshsubmit'
                                                            type='button'
                                                            value='REFRESH'
                                                        >
                                                            <span
                                                                title="Refresh image"
                                                                onmouseover=""
                                                                style="
                                                                    cursor: pointer;
                                                                    display:block;
                                                                    text-align:center;
                                                                    margin:0 auto;
                                                                "
                                                                class='material-symbols-outlined card_detail'>
                                                                refresh
                                                            </span>
                                                        </button>
                                                        <input
                                                            type='hidden'
                                                            id='refreshid'
                                                            value="<?php echo $row[0]; ?>"
                                                        >
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            endif; ?>
                        </table>
                    </div>
                    <div id="carddetailinfo">
                            <?php
                            echo "<h3 class='shallowh3'>Details</h3>";

                            if (isset($admin) and $admin == 1) :
                                echo "<a href='admin/cards.php?cardtoedit=$lookupid'><i><i class='ss ss-$setcode "
                                . "ss-{$row['rarity']} ss-grad ss-2x'></i>&nbsp;$setname ($setcodeupper) no. "
                                . "{$row['number_import']}</i></a><br>";
                            else :
                                echo "<i><i class='ss ss-$setcode ss-{$row['rarity']} ss-grad ss-2x'></i>&nbsp;"
                                . "$setname ($setcodeupper) no. {$row['number_import']}</i><br>";
                            endif;

                            $gametypestring = '';
                            if (str_contains($row['game_types'], 'paper')) :
                                $gametypestring = "Paper";
                            endif;
                            if ($gametypestring !== '' and substr($gametypestring, -2) !== "; ") :
                                $gametypestring .= "; ";
                            endif;
                            if (str_contains($row['game_types'], 'arena')) :
                                $gametypestring .= "MtG Arena";
                            endif;
                            if ($gametypestring !== '' and substr($gametypestring, -2) !== "; ") :
                                $gametypestring .= "; ";
                            endif;
                            if (str_contains($row['game_types'], 'mtgo')) :
                                $gametypestring .= "MtG Online";
                            endif;
                            if ($gametypestring !== '' and substr($gametypestring, -2) !== "; ") :
                                $gametypestring .= "; ";
                            endif;
                            if ($gametypestring !== '' and substr($gametypestring, -2) === "; ") :
                                $gametypestring = substr($gametypestring, 0, -2);
                            endif;
                            if ($gametypestring === '') :
                                $gametypestring = 'None';
                            endif;
                            echo "<b>Game types: </b>$gametypestring<br>";
                            if (isset($full_promo_text) and $full_promo_text !== '') :
                                echo "<b>Promo type: </b>$full_promo_text<br>";
                            endif;
                            if (
                                $row["layout"] !== 'reversible_card'
                                and $row["layout"] !== 'double_faced_token'
                            ) :
                                // no details at card level for reversible cards
                                if (isset($row['type']) and $row['type'] != '') :
                                    echo "<b>Type: </b>" . $row['type'];
                                endif;
                                if (
                                    isset($card_lang)
                                    and $card_lang != ''
                                    and $card_lang != 'en'
                                    and $row['primary_card'] === 1
                                ) :
                                    echo "<br><b>Language: </b>" . $gameRules->getLanguageLabel($card_lang)
                                        . " (primary print)";
                                elseif (isset($card_lang) and $card_lang != '' and $card_lang != 'en') :
                                        echo "<br><b>Language: </b>" . $gameRules->getLanguageLabel($card_lang);
                                endif;
                                    echo "<br>";
                                    echo "<b>Rarity: </b>";
                                if (strpos($row['rarity'], "rare") !== false) :
                                    echo "Rare";
                                elseif (strpos($row['rarity'], "mythic") !== false) :
                                        echo "Mythic Rare";
                                elseif (strpos($row['rarity'], "uncommon") !== false) :
                                        echo "Uncommon";
                                else :
                                        echo "Common";
                                endif;
                                echo "<br>";
                                if (Validation::validateTrueDecimal($row['cmc'], $appConfig) === false) :
                                    $msg->logMessage('[DEBUG]', "Trying to round cmc {$row['cmc']}");
                                    $row['cmc'] = round($row['cmc']);
                                endif;
                                if (!in_array($row['layout'], $rulesTokenLayouts)) :
                                    echo "<b>Mana value: </b>" . $row['cmc'];
                                    echo "<br>";
                                endif;
                            endif;
                            if (in_array($row["layout"], $rulesLayoutsDouble)) :
                                if (
                                    isset($row['f1_flavor_name'])
                                    and $row['f1_flavor_name'] !== null
                                    and $row['f1_flavor_name'] !== ''
                                ) :
                                    echo "<b>Name: </b>{$row['f1_flavor_name']} <i>({$row['f1_name']})</i>";
                                else :
                                    echo "<b>Name: </b>" . $row['f1_name'];
                                endif;
                                echo "<br>";
                                if ($row['layout'] === 'reversible_card') :
                                    if ($row['f1_cmc'] !== null) :
                                        if (Validation::validateTrueDecimal($row['f1_cmc'], $appConfig) === false) :
                                            $msg->logMessage('[DEBUG]', "Trying to round f1_cmc {$row['f1_cmc']}");
                                            $row['f1_cmc'] = round($row['f1_cmc']);
                                        endif;
                                        echo "<b>Mana value: </b>" . $row['f1_cmc'];
                                        echo "<br>";
                                    endif;
                                endif;
                                $manacost = CardUtils::symbolReplaceFont($row['f1_manacost']);
                                if ($manacost !== null and $manacost !== '') :
                                    echo "<b>Mana cost: </b>" . $manacost;
                                    echo "<br>";
                                endif;
                                if (isset($row['f1_type']) and $row['f1_type'] !== null and $row['f1_type'] != '') :
                                    echo "<b>Type: </b>" . $row['f1_type'];
                                    echo "<br>";
                                endif;
                                if (isset($card_lang) and $card_lang != '' and $card_lang != 'en') :
                                    echo "<b>Lang: </b>" . $gameRules->getLanguageLabel($card_lang);
                                    echo "<br>";
                                endif;
                                if ($row['f1_ability'] !== null and $row['f1_ability'] != '') :
                                    $abilityText = $row['f1_ability'];
                                    if ($row['f1_type'] !== null and strpos($row['f1_type'], 'laneswalker') !== false) :
                                        $abilityText = CardUtils::planeswalkerLoyaltyReplace($abilityText, $msg);
                                    endif;
                                    echo "<b>Abilities: </b>" . CardUtils::symbolReplaceFont($abilityText);
                                    echo "<br>";
                                endif;
                                if ($row['f1_type'] !== null and strpos($row['f1_type'], 'reature') !== false) :
                                    echo "<b>Power / Toughness: </b>" . $row['f1_power'] . "/" . $row['f1_toughness'];
                                    echo "<br>";
                                elseif ($row['f1_type'] !== null and strpos($row['f1_type'], 'laneswalker') !== false) :
                                    echo "<b>Loyalty: </b>" . $row['f1_loyalty'];
                                    echo "<br>";
                                elseif ($row['f1_type'] !== null and strpos($row['f1_type'], 'attle') !== false) :
                                    echo "<b>Defense: </b>" . $row['f1_toughness'];
                                    echo "<br>";
                                endif;
                            else :
                                $manacost = CardUtils::symbolReplaceFont($row['manacost']);
                                if ($manacost !== '') :
                                    echo "<b>Mana cost: </b>" . $manacost;
                                    echo "<br>";
                                endif;
                                if ($row['ability'] != '') :
                                    $abilityText = $row['ability'];
                                    if ($row['type'] !== null and strpos($row['type'], 'laneswalker') !== false) :
                                        $abilityText = CardUtils::planeswalkerLoyaltyReplace($abilityText, $msg);
                                    endif;
                                    echo "<b>Abilities: </b>" . CardUtils::symbolReplaceFont($abilityText);
                                    echo "<br>";
                                endif;
                                if (strpos($row['type'], 'reature') !== false) :
                                    echo "<b>Power / Toughness: </b>" . $row['power'] . "/" . $row['toughness'];
                                    echo "<br>";
                                elseif (strpos($row['type'], 'laneswalker') !== false) :
                                    echo "<b>Loyalty: </b>" . $row['loyalty'];
                                    echo "<br>";
                                elseif (strpos($row['type'], 'attle') !== false) :
                                    echo "<b>Defense: </b>" . $row['toughness'];
                                    echo "<br>";
                                endif;
                            endif;
                            if ($meld === 'meld_part') :
                                if ($row['p1_component'] === 'meld_part' and $row['p1_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p1_id'];
                                    $meld_partner_name = $row['p1_name'];
                                elseif ($row['p2_component'] === 'meld_part' and $row['p2_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p2_id'];
                                    $meld_partner_name = $row['p2_name'];
                                elseif ($row['p3_component'] === 'meld_part' and $row['p3_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p3_id'];
                                    $meld_partner_name = $row['p3_name'];
                                elseif ($row['p4_component'] === 'meld_part' and $row['p4_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p4_id'];
                                    $meld_partner_name = $row['p4_name'];
                                elseif ($row['p5_component'] === 'meld_part' and $row['p5_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p5_id'];
                                    $meld_partner_name = $row['p5_name'];
                                elseif ($row['p6_component'] === 'meld_part' and $row['p6_name'] !== $row['name']) :
                                    $meld_partner_id = $row['p6_id'];
                                    $meld_partner_name = $row['p6_name'];
                                else :
                                    $meld_partner_id = $row['p7_id'];
                                    $meld_partner_name = $row['p7_name'];
                                endif;
                                echo "<b>Melds with:</b><br>";
                                echo "<a href='carddetail.php?id=$meld_partner_id'>$meld_partner_name</a>&nbsp;<br>";
                                echo "<b>to:</b><br>";
                                if ($row['p1_component'] === 'meld_result') :
                                    $meld_result_id = $row['p1_id'];
                                    $meld_result_name = $row['p1_name'];
                                elseif ($row['p2_component'] === 'meld_result') :
                                    $meld_result_id = $row['p2_id'];
                                    $meld_result_name = $row['p2_name'];
                                elseif ($row['p3_component'] === 'meld_result') :
                                    $meld_result_id = $row['p3_id'];
                                    $meld_result_name = $row['p3_name'];
                                elseif ($row['p4_component'] === 'meld_result') :
                                    $meld_result_id = $row['p4_id'];
                                    $meld_result_name = $row['p4_name'];
                                elseif ($row['p5_component'] === 'meld_result') :
                                    $meld_result_id = $row['p5_id'];
                                    $meld_result_name = $row['p5_name'];
                                elseif ($row['p6_component'] === 'meld_result') :
                                    $meld_result_id = $row['p6_id'];
                                    $meld_result_name = $row['p6_name'];
                                else :
                                    $meld_result_id = $row['p7_id'];
                                    $meld_result_name = $row['p7_name'];
                                endif;
                                echo "<a href='carddetail.php?id=$meld_result_id'>$meld_result_name</a>&nbsp;<br>";
                            elseif ($meld === 'meld_result') :
                                echo "<b>Melds from:</b><br>";
                                if ($row['p1_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p1_id']}'>{$row['p1_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p2_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p2_id']}'>{$row['p2_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p3_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p3_id']}'>{$row['p3_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p4_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p4_id']}'>{$row['p4_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p5_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p5_id']}'>{$row['p5_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p6_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p6_id']}'>{$row['p6_name']}</a>&nbsp;<br>";
                                endif;
                                if ($row['p7_component'] === 'meld_part') :
                                    echo "<a href='carddetail.php?id={$row['p7_id']}'>{$row['p7_name']}</a>&nbsp;<br>";
                                endif;
                            endif;
                            if ($row['artist'] != '') :
                                echo "<b>Art by: </b>" . $row['artist'];
                                echo "<br>";
                            endif;
                            if ((substr($row['type'], 0, 6) != 'Plane ') and $row['type'] != 'Phenomenon') :
                                echo "<b>Legal in: </b>";
                                $msg->logMessage('[DEBUG]', "Getting legalities for $setcode, $cardname, $id");
                                $legalitystring = '';

                                if ($row['legalitystandard'] == 'legal') :
                                    $legalitystring = "Standard";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalityalchemy'] == 'legal') :
                                    $legalitystring .= "Alchemy";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalityhistoric'] == 'legal') :
                                    $legalitystring .= "Historic";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalitypioneer'] == 'legal') :
                                    $legalitystring .= "Pioneer";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalitymodern'] == 'legal') :
                                    $legalitystring .= "Modern";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalitycommander'] == 'legal') :
                                    $legalitystring .= "Commander";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalityvintage'] == 'legal') :
                                    $legalitystring .= "Vintage";
                                elseif ($row['legalityvintage'] == 'restricted') :
                                    $legalitystring .= "Vintage: restricted";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) !== "; ") :
                                    $legalitystring .= "; ";
                                endif;

                                if ($row['legalitylegacy'] == 'legal') :
                                    $legalitystring .= "Legacy";
                                elseif ($row['legalitylegacy'] == 'restricted') :
                                    $legalitystring .= "Legacy: restricted";
                                endif;

                                if ($legalitystring !== '' and substr($legalitystring, -2) === "; ") :
                                    $legalitystring = substr($legalitystring, 0, -2);
                                endif;

                                if ($legalitystring === '') :
                                    $legalitystring = 'None';
                                endif;

                                echo $legalitystring . "<br>";
                            endif;
                            if ($row['layout'] === 'adventure') :
                                echo "<h3>Adventure: </h3>";
                                echo "<b>Name: </b>" . $row['f2_name'];
                                echo "<br>";
                                $flipmanacost = CardUtils::symbolReplaceFont($row['f2_manacost']);
                                if ($flipmanacost !== '') :
                                    echo "<b>Mana cost: </b>" . $flipmanacost;
                                    echo "<br>";
                                endif;
                                if (isset($row['f2_type']) and $row['f2_type'] != '') :
                                    echo "<b>Type: </b>" . $row['f2_type'];
                                    echo "<br>";
                                endif;
                                if (isset($card_lang) and $card_lang != '' and $card_lang != 'en') :
                                    echo "<b>Lang: </b>" . $gameRules->getLanguageLabel($card_lang);
                                    echo "<br>";
                                endif;
                                if (isset($flipability) and $flipability != '') :
                                    $flipabilityText = $flipability;
                                    if (
                                        isset($row['f2_type'])
                                        and strpos($row['f2_type'], 'laneswalker') !== false
                                    ) :
                                        $flipabilityText = CardUtils::planeswalkerLoyaltyReplace(
                                            $flipabilityText,
                                            $msg
                                        );
                                    endif;
                                    $flipabilityText = CardUtils::symbolReplaceFont($flipabilityText);
                                    echo "<b>Abilities: </b>" . $flipabilityText;
                                    echo "<br>";
                                endif;
                                if (strpos($row['f2_type'], 'reature') !== false) :
                                    echo "<b>Power / Toughness: </b>"
                                    . $row['f1_power'] . "/" . $row['f2_toughness'] . "<br>";
                                elseif (strpos($row['f2_type'], 'laneswalker') !== false) :
                                    echo "<b>Loyalty: </b>" . $row['f2_loyalty'] . "<br>";
                                endif;
                            elseif ($row['layout'] === 'split' or $row['layout'] === 'flip') :
                                echo "<br><b>Name: </b>" . $row['f2_name'];
                                echo "<br>";
                                $flipmanacost = CardUtils::symbolReplaceFont($row['f2_manacost']);
                                if ($flipmanacost !== '') :
                                    echo "<b>Mana cost: </b>" . $flipmanacost;
                                    echo "<br>";
                                endif;
                                if (isset($row['f2_type']) and $row['f2_type'] != '') :
                                    echo "<b>Type: </b>" . $row['f2_type'];
                                    echo "<br>";
                                endif;
                                if (isset($card_lang) and $card_lang != '' and $card_lang != 'en') :
                                    echo "<b>Lang: </b>" . $gameRules->getLanguageLabel($card_lang);
                                    echo "<br>";
                                endif;
                                if (isset($flipability) and $flipability != '') :
                                    $flipabilityText = $flipability;
                                    if (
                                        isset($row['f2_type'])
                                        and strpos($row['f2_type'], 'laneswalker') !== false
                                    ) :
                                        $flipabilityText = CardUtils::planeswalkerLoyaltyReplace(
                                            $flipabilityText,
                                            $msg
                                        );
                                    endif;
                                    $flipabilityText = CardUtils::symbolReplaceFont($flipabilityText);
                                    echo "<b>Abilities: </b>" . $flipabilityText;
                                    echo "<br>";
                                endif;
                                if (strpos($row['f2_type'], 'reature') !== false) :
                                    echo "<b>Power / Toughness: </b>"
                                    . $row['f1_power'] . "/" . $row['f2_toughness'] . "<br>";
                                elseif (strpos($row['f2_type'], 'laneswalker') !== false) :
                                    echo "<b>Loyalty: </b>" . $row['f2_loyalty'] . "<br>";
                                endif;
                            endif;
                            ?>
                    </div><?php
                    if (($meld !== 'meld_result') and ($not_paper !== true ) and ($cardtypes != 'none' )) : ?>
                        <div id="carddetailupdate">
                            <h3 class="shallowh3">My collection</h3>
                            <?php
                            $msg->logMessage('[DEBUG]', "Card types: $cardtypes");
                            $cellid = "cell" . $id;
                            $cellid_one = $cellid . '_one';
                            $cellid_two = $cellid . '_two';
                            $cellid_three = $cellid . '_three';
                            $cellid_one_flash = $cellid_one;
                            $cellid_two_flash = $cellid_two;
                            $cellid_three_flash = $cellid_three;
                            $cardIdEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
                            $cellidOneEsc = htmlspecialchars($cellid_one, ENT_QUOTES, 'UTF-8');
                            $cellidTwoEsc = htmlspecialchars($cellid_two, ENT_QUOTES, 'UTF-8');
                            $cellidThreeEsc = htmlspecialchars($cellid_three, ENT_QUOTES, 'UTF-8');
                            $cellidOneFlashEsc = htmlspecialchars($cellid_one_flash, ENT_QUOTES, 'UTF-8');
                            $cellidTwoFlashEsc = htmlspecialchars($cellid_two_flash, ENT_QUOTES, 'UTF-8');
                            $cellidThreeFlashEsc = htmlspecialchars($cellid_three_flash, ENT_QUOTES, 'UTF-8');
                            ?>
                            <table>
                                <tr class='bulksubmitrowsmall'>
                                    <td class='bulksubmittd' id="<?php echo $cellid . "td_one"; ?>">
                                        <?php
                                        if ($meld === 'meld_result') :
                                            echo "Meld card";
                                        elseif ($not_paper == true) :
                                            echo "<i>MtG Arena/Online</i>";
                                        elseif ($cardtypes === 'foilonly') :
                                            $poststring = 'newfoil';
                                            echo "Foil: <input class='bulkinputsmall foil js-ajax-update' "
                                                . "id='$cellid_one' type='number' step='1' min='0' name='myfoil' "
                                                . "value='$myfoil' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidOneEsc' "
                                                . "data-ajax-flash='$cellidOneFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        elseif ($cardtypes === 'etchedonly') :
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch js-ajax-update' "
                                                . "id='$cellid_one' type='number' step='1' min='0' name='myetch' "
                                                . "value='$myetch' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidOneEsc' "
                                                . "data-ajax-flash='$cellidOneFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else :
                                            $poststring = 'newqty';
                                            echo "Normal: <input class='bulkinputsmall normal js-ajax-update' "
                                                . "id='$cellid_one' type='number' step='1' min='0' name='myqty' "
                                                . "value='$myqty' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidOneEsc' "
                                                . "data-ajax-flash='$cellidOneFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        endif;?>
                                    </td>
                                    <td class='bulksubmittdsmall' id="<?php echo $cellid . "td_two"; ?>">
                                        <?php
                                        if ($meld === 'meld_result') :
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'foilonly') :
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'normalonly') :
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'etchedonly') :
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'normaletched') :
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch js-ajax-update' "
                                                . "id='$cellid_two' type='number' step='1' min='0' name='myetch' "
                                                . "value='$myetch' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidTwoEsc' "
                                                . "data-ajax-flash='$cellidTwoFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else :
                                            $poststring = 'newfoil';
                                            echo "Foil: <input class='bulkinputsmall foil js-ajax-update' "
                                                . "id='$cellid_two' type='number' step='1' min='0' name='myfoil' "
                                                . "value='$myfoil' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidTwoEsc' "
                                                . "data-ajax-flash='$cellidTwoFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        endif;?>
                                    </td>
                                    <td class='bulksubmittdsmall' id="<?php echo $cellid . "td_three"; ?>">
                                        <?php
                                        if ($cardtypes === 'normalfoiletched') :
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch js-ajax-update' "
                                                . "id='$cellid_three' type='number' step='1' min='0' name='myetch' "
                                                . "value='$myetch' data-ajax-cardid='$cardIdEsc' "
                                                . "data-ajax-cellid='$cellidThreeEsc' "
                                                . "data-ajax-flash='$cellidThreeFlashEsc' "
                                                . "data-ajax-post='$poststring'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else :
                                            echo "&nbsp;";
                                        endif;?>
                                    </td>
                                </tr>
                            </table>
                            <form id="updatenotesform" action="?" method="POST">
                                <table style="margin-top:10px"><?php
                                    $notesEsc = CardUtils::escapeCardNotesForTextarea($notes);
                                    echo "<tr><td><textarea class='textinput' id='cardnotes' name='notes' rows='2' "
                                        . "cols='40' placeholder='My notes'>$notesEsc</textarea></td></tr>"; ?>
                                </table> <?php
                                echo "<input type='hidden' name='id' value=" . $lookupid . ">"; ?>
                                <input
                                    class='inline_button stdwidthbutton updatebutton'
                                    style="cursor: pointer;"
                                    type="hidden"
                                    id="hiddenSubmitValue"
                                    value="UPDATE NOTES"
                                >
                                <button
                                    class='inline_button save_icon'
                                    type="button"
                                    title="Save"
                                    disabled
                                ><span class="material-symbols-outlined">save</span></button>
                            </form>
                            <div
                                id="carddetail-bulk-config"
                                data-cardid="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cardtypes="<?php echo htmlspecialchars($cardtypes, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cellid-one="<?php echo htmlspecialchars($cellid_one, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cellid-two="<?php echo htmlspecialchars($cellid_two, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cellid-three="<?php echo htmlspecialchars($cellid_three, ENT_QUOTES, 'UTF-8'); ?>"
                                data-myqty="<?php echo htmlspecialchars((string) $myqty, ENT_QUOTES, 'UTF-8'); ?>"
                                data-myfoil="<?php echo htmlspecialchars((string) $myfoil, ENT_QUOTES, 'UTF-8'); ?>"
                                data-myetch="<?php echo htmlspecialchars((string) $myetch, ENT_QUOTES, 'UTF-8'); ?>"
                            ></div>
                            <hr class='hr324'>
                            <?php

                            // Price section
                            if (
                                isset($scryfallresult["price"])
                                and $scryfallresult["price"] !== ""
                                and $scryfallresult["price"] != 0.00
                                and $scryfallresult["price"] !== null
                                and str_contains($cardtypes, 'normal')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using Scryfall normal price");
                                $normalprice = number_format($scryfallresult['price'], 2);
                                $localnormal = number_format(($scryfallresult["price"] * $rate), 2, '.', ',');
                            elseif (
                                isset($row["price"])
                                and $row["price"] !== ""
                                and $row["price"] != 0.00
                                and str_contains($cardtypes, 'normal')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using database normal price");
                                $normalprice = number_format($row['price'], 2);
                                $localnormal = number_format(($row["price"] * $rate), 2, '.', ',');
                            else :
                                $msg->logMessage('[DEBUG]', "No normal price");
                                $normalprice = false;
                            endif;
                            if (
                                isset($scryfallresult["price_foil"])
                                and $scryfallresult["price_foil"] !== ""
                                and $scryfallresult["price_foil"] != 0.00
                                and $scryfallresult["price_foil"] !== null
                                and str_contains($cardtypes, 'foil')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using Scryfall foil price");
                                $foilprice = number_format($scryfallresult['price_foil'], 2);
                                ;
                                $localfoil = number_format(($scryfallresult["price_foil"] * $rate), 2, '.', ',');
                            elseif (
                                isset($row["price_foil"])
                                and $row["price_foil"] !== ""
                                and $row["price_foil"] != 0.00
                                and str_contains($cardtypes, 'foil')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using database foil price");
                                $foilprice = number_format($row['price_foil'], 2);
                                $localfoil = number_format(($row["price_foil"] * $rate), 2, '.', ',');
                            else :
                                $msg->logMessage('[DEBUG]', "No foil price");
                                $foilprice = false;
                            endif;
                            if (
                                isset($scryfallresult["price_etched"])
                                and $scryfallresult["price_etched"] !== ""
                                and $scryfallresult["price_etched"] != 0.00
                                and $scryfallresult["price_etched"] !== null
                                and str_contains($cardtypes, 'etch')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using Scryfall etched price");
                                $etchprice = number_format($scryfallresult['price_etched'], 2);
                                $localetched = number_format(($scryfallresult["price_etched"] * $rate), 2, '.', ',');
                            elseif (
                                isset($row["price_etched"])
                                and $row["price_etched"] !== ""
                                and $row["price_etched"] != 0.00
                                and str_contains($cardtypes, 'etch')
                            ) :
                                $msg->logMessage('[DEBUG]', "Using database etched price");
                                $etchprice = number_format($row['price_etched'], 2);
                                $localetched = number_format(($row["price_etched"] * $rate), 2, '.', ',');
                            else :
                                $msg->logMessage('[DEBUG]', "No etched price");
                                $etchprice = false;
                            endif;?>
                            <div id="priceblock" data-cardid="<?php echo $id; ?>">
                            <?php
                            if ($normalprice == false and $foilprice == false and $etchprice == false) : ?>
                                <table id='tcgplayer' width="100%">
                                    <tr>
                                        <td colspan="2" class="buycellleft">
                                            No prices available <br>
                                        </td>
                                    </tr>
                                </table> <?php
                            else : ?>
                                <table id='tcgplayer' width="100%">
                                    <tr>
                                        <td>
                                            <b>Price</b>
                                        </td>
                                        <td>
                                            <b>USD
                                                <?php
                                                if ($fx === true) :
                                                    echo "($targetCurrency)";
                                                endif;
                                                ?>
                                            </b>
                                        </td>
                                    </tr> <?php

                                    $fxUpdating = ($fxPending === true && $fxMissing === true);
                                    $fxUpdatingLabel = '<span class="fx-pending">Updating</span>';

                                    if ($normalprice !== false) : ?>
                                    <tr>
                                        <td class="buycellleft">
                                            Normal
                                        </td>
                                        <td class="buycell mid">
                                            <?php
                                            if ($fx === true) :
                                                echo $normalprice . " ($localnormal)";
                                            elseif ($fxUpdating === true) :
                                                echo $normalprice . " ($fxUpdatingLabel)";
                                            else :
                                                echo $normalprice;
                                            endif;
                                            ?>
                                        </td>
                                    </tr> <?php
                                    endif;

                                    if ($foilprice !== false) : ?>
                                    <tr>
                                        <td class="buycellleft">
                                        Foil
                                    </td>
                                    <td class="buycell mid">
                                        <?php
                                        if ($fx === true) :
                                            echo $foilprice . " ($localfoil)";
                                        elseif ($fxUpdating === true) :
                                            echo $foilprice . " ($fxUpdatingLabel)";
                                        else :
                                            echo $foilprice;
                                        endif;
                                        ?>
                                    </td>
                                </tr> <?php
                                    endif;

                                    if ($etchprice !== false) :?>
                                    <tr>
                                        <td class="buycellleft">
                                        Etched
                                    </td>
                                    <td class="buycell mid">
                                        <?php
                                        if ($fx === true) :
                                            echo $etchprice . " ($localetched)";
                                        elseif ($fxUpdating === true) :
                                            echo $etchprice . " ($fxUpdatingLabel)";
                                        else :
                                            echo $etchprice;
                                        endif;
                                        ?>
                                    </td>
                                </tr> <?php
                                    endif; ?>
                                </table> <?php
                            endif;?>
                            </div>
                            <?php
                            if (isset($tcg_buy_uri) and $tcg_buy_uri !== "") :
                                $tcgdirectlink = $tcg_buy_uri;
                            else :
                                $tcgdirectlink = null;
                            endif; ?>

                            <hr class='hr324'>
                            <b>Printings & links</b>
                            <table width="100%">
                                <tr>
                                    <td class="buycellleft">
                                        <?php echo "<a href='index.php?name=" . $row['name']
                                            . "&amp;exact=yes'>Primary language </a>"; ?>
                                    </td>
                                    <td class="buycellleft">
                                        <?php echo "<a href='index.php?name=" . $row['name']
                                            . "&amp;allprintings=yes'>All languages </a>"; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="buycellleft">
                                        <?php
                                        if (isset($row['scryfall_uri']) and $row['scryfall_uri'] !== "") :
                                            echo "<a href='" . $row['scryfall_uri'] . "' target='_blank'>Scryfall</a>";
                                        else :
                                            $namehtml = str_replace("//", "", $namehtml);
                                            $namehtml = str_replace("  ", "%20", $namehtml);
                                            $namehtml = str_replace(" ", "%20", $namehtml);
                                            echo "<a href='https://magiccards.info/query?q=" . $namehtml
                                                . "' target='_blank'>Search Scryfall</a>";
                                        endif;?>
                                    </td>
                                    <td class="buycellleft">
                                        <a
                                            id="tcgplayerlink"
                                            href="<?php echo $tcgdirectlink ?? '#'; ?>"
                                            target="_blank"
                                            data-loading="<?php echo ($tcgdirectlink === null) ? '1' : '0'; ?>"
                                            style="<?php echo ($tcgdirectlink === null)
                                                ? 'opacity:0.6;pointer-events:none;'
                                                : ''; ?>"
                                        ><?php echo ($tcgdirectlink === null) ? 'TCGPlayer (loading)' : 'TCGPlayer'; ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <?php

                            // Others with this card section
                            if ($groupInOut === 1 && $groupId > 0) :
                                $usergroup = $groupId;
                                $msg->logMessage('[DEBUG]', "Groups are active, group ID = $usergroup");
                                $grpquery = "SELECT usernumber, username, status, groupid, groupname, owner FROM users "
                                    . "LEFT JOIN `groups` ON users.groupid = groups.groupnumber "
                                    . "WHERE groupid = ? AND usernumber <> ?";
                                $grpparams = [$usergroup,$_SESSION["user"]];
                                if ($sqluserqry = $db->execute_query($grpquery, $grpparams)) :
                                    $msg->logMessage('[DEBUG]', "SQL query succeeded");
                                else :
                                    throw new Exception(
                                        "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: "
                                            . $db->error
                                    );
                                endif;
                                $others = 0;
                                $q = 0;
                                $first = true;

                                while ($userrow = $sqluserqry->fetch_array(MYSQLI_ASSOC)) :
                                    if ($userrow['status'] !== 'disabled') :
                                        $msg->logMessage('[DEBUG]', "Scanning " . $userrow['username'] . "'s cards");
                                        $grpuser[$q]['id'] = $userrow['usernumber'];
                                        $grpuser[$q]['name'] = $userrow['username'];
                                        $q = $q + 1;
                                        $usertable = $userrow['usernumber'] . 'collection';
                                        $sqlqry = "SELECT id,normal,foil,etched,notes,topvalue FROM `$usertable` "
                                            . "WHERE id = ?";
                                        $sqlparams = [$id];
                                        if ($sqlqtyqry = $db->execute_query($sqlqry, $sqlparams)) :
                                            $msg->logMessage(
                                                '[DEBUG]',
                                                "SQL query succeeded for {$userrow['username']}, $row[0]"
                                            );
                                        else :
                                            throw new Exception(
                                                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: "
                                                    . $db->error
                                            );
                                        endif;
                                        if ($sqlqtyqry->num_rows !== 0) :
                                            $userqtyresult = $sqlqtyqry->fetch_array(MYSQLI_ASSOC);
                                            if (($userqtyresult['normal'] > 0) or ($userqtyresult['foil'] > 0)) :
                                                if (empty($userqtyresult['normal'])) :
                                                    $userqtyresult['normal'] = 0;
                                                endif;
                                                if (empty($userqtyresult['foil'])) :
                                                    $userqtyresult['foil'] = 0;
                                                endif;
                                                if (empty($userqtyresult['etched'])) :
                                                    $userqtyresult['etched'] = 0;
                                                endif;
                                                $others = 1;
                                                $userrow['username'] = htmlentities(
                                                    $userrow['username'],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                $userqtyresult['normal'] = htmlentities(
                                                    $userqtyresult['normal'],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                $userqtyresult['foil'] = htmlentities(
                                                    $userqtyresult['foil'],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                $userqtyresult['etched'] = htmlentities(
                                                    $userqtyresult['etched'],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );
                                                if ($first === true) :
                                                    echo "<hr class='hr324'>";
                                                    echo "<b>Others with this card</b><br>";
                                                    $first = false;
                                                endif;
                                                echo ucfirst($userrow['username'])
                                                    . ": &nbsp;<i>Normal:</i> {$userqtyresult['normal']} "
                                                    . "&nbsp;&nbsp;<i>Foil:</i> {$userqtyresult['foil']} "
                                                    . "&nbsp;&nbsp;<i>Etch:</i> {$userqtyresult['etched']}<br>";
                                            endif;
                                        endif;
                                    endif;
                                endwhile;
                                if ($others == 0) :
                                    // echo "N/A<br>";
                                endif;
                            else :
                                // echo "<b>Others with this card</b><br>";
                                // echo "Opt in for groups in Profile";
                            endif;
                            ?>
                            <hr class='hr324'>
                            <?php
                            $msg->logMessage('[NOTICE]', "Decks enabled: $decks_on");
                            if (in_array($row['layout'], $rulesTokenLayouts)) :
                                $decks_on = 0;
                            endif;
                            if ($decks_on === 1) :
                                echo "<div id='deckadd'>";
                                if (isset($decktoaddto)) :
                                    $msg->logMessage(
                                        '[NOTICE]',
                                        "Received request to add $deckqty x card $cardId to deck: '$decktoaddto'; "
                                        . "Newdeck: '$newdeckname'"
                                    );
                                    // If the deck is new, is the new name unique? If yes, create it.
                                    if ($decktoaddto == "newdeck") :
                                        $msg->logMessage(
                                            '[NOTICE]',
                                            "Calling Deckmanager->addDeck: '$user/$newdeckname'"
                                        );
                                        $obj = new DeckManager(
                                            $db,
                                            $appConfig,
                                            $gameRules,
                                            $userEmail
                                        );
                                        $decksuccess = $obj->addDeck($user, $newdeckname);
                                        if ($decksuccess['flag'] === 1) :
                                            $decktoaddto = $decksuccess['decknumber'];
                                        else :
                                                $decktoaddto = null;
                                        endif;
                                    else :
                                            // Check that the proposed deck exists and belongs to owner.
                                            $obj = new DeckManager(
                                                $db,
                                                $appConfig,
                                                $gameRules,
                                                $userEmail
                                            );
                                        if ($obj->assertDeckOwner($decktoaddto, $user, 'carddetail.php') === false) : ?>
                                                <div class="msg-new error-new">
                                                    <span>You don't have that deck</span>
                                                    <br>
                                                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                </div>
                                                <?php
                                                $decksuccess = [
                                                'decknumber' => null,
                                                'flag' => 10
                                                ];
                                        else :
                                                $decksuccess = [
                                                    'decknumber' => null,
                                                    'flag' => 2
                                                ];
                                        endif;
                                    endif;
                                        // Deck status: created (1), failed (10), or confirmed ownership/existence (2)
                                        $msg->logMessage('[NOTICE]', "Decksuccess code is {$decksuccess['flag']}");
                                    if ($decksuccess['flag'] !== 10) :  // Deck exists and belongs to the caller
                                        if ($decksuccess['flag'] === 2) : // Not a new deck, run card check
                                            $msg->logMessage(
                                                '[NOTICE]',
                                                "Running SQL to see if $cardId is already in deck $decktoaddto"
                                            );

                                            $sql = "SELECT cardnumber FROM deckcards "
                                                . "WHERE decknumber = ? AND cardnumber = ? "
                                                . "AND ((cardqty IS NOT NULL) OR (sideqty IS NOT NULL))";
                                            $params = [$decktoaddto,$cardId];
                                            $resultchk = $db->execute_query($sql, $params);
                                            if ($resultchk !== false && $resultchk->num_rows === 1) :
                                                $cardcheckrow = $resultchk->fetch_assoc();
                                                $msg->logMessage(
                                                    '[NOTICE]',
                                                    "{$cardcheckrow['cardnumber']} is already in that deck"
                                                );
                                                ?>
                                                <div class="msg-new error-new">
                                                    <span>Card already in deck</span>
                                                    <br>
                                                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                </div>
                                                <?php
                                                $cardchecksuccess = 0;
                                            elseif ($resultchk !== false && $resultchk->num_rows === 0) :
                                                    $msg->logMessage(
                                                        '[NOTICE]',
                                                        "Card is not in the deck, proceeding to write"
                                                    );
                                                    $cardchecksuccess = 1;
                                            else :
                                                    $errmsg = "[ERROR]" . basename(__FILE__) . " " . __LINE__
                                                        . ": SQL failure: " . $db->error;
                                                    throw new Exception($errmsg);
                                            endif;
                                        elseif ($decksuccess['flag'] === 1) :
                                                $cardchecksuccess = 2;
                                        endif;
                                            //Insert card to deck
                                        if (in_array($cardchecksuccess, [1, 2])) :
                                            $deckqty = (int)$deckqty;

                                            //Call add card function
                                            $obj = new DeckManager(
                                                $db,
                                                $appConfig,
                                                $gameRules,
                                                $userEmail
                                            );
                                            $obj->addDeckCard($decktoaddto, $cardId, 'main', $deckqty);

                                            //Check it's added
                                            $sql = "SELECT cardnumber,cardqty FROM deckcards WHERE decknumber = ? "
                                                . "AND cardnumber = ? AND cardqty = ? LIMIT 1";
                                            $params = [$decktoaddto,$cardId,$deckqty];
                                            $resultchksql = $db->execute_query($sql, $params);
                                            if ($resultchksql !== false && $resultchksql->num_rows === 1) :
                                                $msg->logMessage('[DEBUG]', "SQL select for card succeeded");
                                                $resultchkins = $resultchksql->fetch_assoc();
                                                if (
                                                    ($resultchkins['cardnumber'] == $cardId)
                                                    and ($resultchkins['cardqty'] == $deckqty)
                                                ) :
                                                    ?>
                                                        <div class="msg-new success-new">
                                                            <span>Card added</span>
                                                            <br>
                                                            <p
                                                                onmouseover=""
                                                                style="cursor: pointer;"
                                                                id='dismiss'
                                                            >OK</p>
                                                        </div>
                                                    <?php
                                                    $msg->logMessage(
                                                        '[NOTICE]',
                                                        "Card $cardId added to deck $decktoaddto"
                                                    );
                                                else :?>
                                                    <div class="msg-new warning-new">
                                                        <span>Card in deck, but quantity mismatch</span>
                                                        <br>
                                                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                    </div>
                                                    <?php
                                                    $msg->logMessage(
                                                        '[NOTICE]',
                                                        "Card $cardId in deck $decktoaddto, but quantity mismatch"
                                                    );
                                                endif;
                                            else :
                                                ?>
                                                    <div class="msg-new error-new">
                                                        <span>Card not added</span>
                                                        <br>
                                                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                    </div>
                                                    <?php
                                                    $msg->logMessage(
                                                        '[ERROR]',
                                                        "Card $cardId was not added to deck $decktoaddto"
                                                    );
                                            endif;
                                        endif;
                                    endif;
                                endif;
                                $msg->logMessage('[NOTICE]', "Checking to see if $cardId is in any owned decks");
                                $obj = new DeckManager(
                                    $db,
                                    $appConfig,
                                    $gameRules,
                                    $userEmail
                                );
                                $inmydecks = $obj->deckCardCheck($cardId, $user);
                                echo "<b>Decks</b><br>";
                                if (!empty($inmydecks)) :
                                    foreach ($inmydecks as $decksrow) :
                                        if ($decksrow['qty'] != '') :
                                            echo "<a href='/deckdetail.php?deck={$decksrow['decknumber']}'>"
                                                . "{$decksrow['deckname']}</a> (main x{$decksrow['qty']}) <br>";
                                        else :
                                                echo "<a href='/deckdetail.php?deck={$decksrow['decknumber']}'>"
                                                    . "{$decksrow['deckname']}</a> "
                                                    . "(sideboard x{$decksrow['sideqty']}) <br>";
                                        endif;
                                    endforeach;
                                endif;
                                $t = 0;
                                $grpdecks = array();
                                if (isset($grpuser)) :
                                    foreach ($grpuser as $decksgrprow) :
                                        $grpuserid = $grpuser[$t]['id'];
                                        $grpusername = ucfirst($grpuser[$t]['name']);
                                        $msg->logMessage('[DEBUG]', "Checking user $grpusername for $cardId");
                                        $obj = new DeckManager(
                                            $db,
                                            $appConfig,
                                            $gameRules,
                                            $userEmail
                                        );
                                        $ingrpdecks = $obj->deckCardCheck($cardId, $grpuserid);
                                        $t = $t + 1;
                                        if (!empty($ingrpdecks)) :
                                            foreach ($ingrpdecks as $decksgrprow) :
                                                if ($decksgrprow['qty'] != '') :
                                                    echo "<i>Group:</i> $grpusername: {$decksgrprow['deckname']} "
                                                        . "(main x{$decksgrprow['qty']}) <br>";
                                                else :
                                                        echo "<i>Group:</i> $grpusername: {$decksgrprow['deckname']} "
                                                            . "(sideboard x{$decksgrprow['sideqty']}) <br>";
                                                endif;
                                            endforeach;
                                        endif;
                                    endforeach;
                                endif;
                                ?>

                                    <!-- Display Add to Deck form -->

                                    <form id="addtodeck" action="<?php echo basename(__FILE__); ?>#deck" method="GET">
                                    <?php
                                    echo "<input type='hidden' name='setabbrv' value=" . $row['cs_setcode'] . ">";
                                    echo "<input type='hidden' name='number' value=" . $row['number'] . ">";
                                    echo "<input type='hidden' name='id' value=" . $row[0] . ">";
                                    ?>
                                        <select id='deckselect' name='decktoaddto'>
                                            <option value='none'>Add...</option>
                                            <option value='newdeck'>Add to new deck...</option>
                                            <?php

                                            $sql = "SELECT decknumber,deckname FROM decks WHERE owner = ? "
                                            . "ORDER BY deckname ASC";
                                            $params = [$user];
                                            $decklistsql = $db->execute_query($sql, $params);

                                            if ($decklistsql !== false) :
                                                $msg->logMessage('[DEBUG]', "SQL select for card succeeded");
                                                while ($dlrow = $decklistsql->fetch_assoc()) :
                                                    $dlrow['decknumber'] = htmlentities(
                                                        $dlrow['decknumber'],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    );
                                                    $dlrow['deckname'] = htmlentities(
                                                        $dlrow['deckname'],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    );
                                                    echo "<option value='{$dlrow['decknumber']}'>"
                                                        . "{$dlrow['deckname']}</option>";
                                                endwhile;
                                            endif;
                                            ?>
                                        </select>
                                        <span id="deckqtyspan" style="display: none">
                                            &nbsp;Qty <input
                                                class='textinput'
                                                id='deckqty'
                                                type='number'
                                                min='0'
                                                disabled
                                                placeholder='N/A'
                                                name='deckqty'
                                                value=""
                                            >
                                            <br>
                                        </span>
                                        <span id="newdecknamespan" style="display: none">
                                            <input
                                                class='textinput'
                                                id='newdeckname'
                                                disabled
                                                type='text'
                                                name='newdeckname'
                                                placeholder='N/A'
                                                size='19'
                                                style="padding-top: 10px;"
                                            />
                                        </span>
                                        <span id="addtodecksubmitspan" style="display: none">
                                            <input
                                                class='importlabel'
                                                id="addtodeckbutton"
                                                disabled
                                                type="submit"
                                                value="ADD TO DECK"
                                                style="margin-top: 10px;"
                                            >
                                        </span>
                                    </form>
                                    </div>
                                    <?php
                            endif; ?>
                            </div>
                        <?php
                    endif; ?>
                </div>

                <!-- Rulings -->

                <div id="carddetailrulings">
                        <?php
                        $ruling_sql = "SELECT source,published_at,comment FROM rulings_scry WHERE oracle_id = ?";
                        $stmt = $db->prepare($ruling_sql);
                        $ruling = '';
                        if ($stmt === false) :
                            throw new Exception(
                                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": Preparing SQL: " . $db->error
                            );
                        endif;
                        $bind = $stmt->bind_param('s', $row['oracle_id']);
                        if ($bind === false) :
                            throw new Exception(
                                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": Binding SQL: " . $db->error
                            );
                        endif;
                        $exec = $stmt->execute();
                        if ($exec === false) :
                            throw new Exception(
                                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": Executing SQL: " . $db->error
                            );
                        else :
                            $result = $stmt->get_result();
                            $msg->logMessage('[NOTICE]', "Rulings: {$result->num_rows} ({$row['oracle_id']})");
                            if (($result->num_rows === 0) and !in_array($row['layout'], $rulesTwoCardDetailSections)) :
                                // no rulings ?>
                            <div>
                            <h3 class='shallowh3'>Rulings</h3>&nbsp;
                            None
                            </div> <?php
                            elseif ($result->num_rows === 0) :
                                // no rulings
                            else :
                                echo("<div>");
                                while ($rulingrow = $result->fetch_array(MYSQLI_ASSOC)) :
                                    // Convert yyyy/mm/dd to dd/mm/yyyy
                                    $olddateparts = explode('-', $rulingrow['published_at']);
                                    $newdate = "<b>" . $olddateparts[2] . '-' . $olddateparts[1] . '-'
                                        . $olddateparts[0] . "</b>";
                                    if ($rulingrow['source'] === 'wotc') :
                                        $source = 'WOTC';
                                    elseif ($rulingrow['source'] === 'scryfall') :
                                        $source = 'Scryfall';
                                    else :
                                        $source = $rulingrow['source'];
                                    endif;
                                    $ruling = $ruling . $newdate . ": "
                                        . CardUtils::symbolReplaceFont($rulingrow['comment'])
                                        . " (" . $source . ")<br>";
                                endwhile;
                                $ruling = TextHelper::autoLink(
                                    $ruling,
                                    array(
                                        "target" => "_blank",
                                        "rel" => "nofollow"
                                    )
                                );
                                if (!in_array($row['layout'], $rulesTwoCardDetailSections)) :
                                    echo "<h3 class='shallowh3'>Rulings:</h3> " . $ruling . "&nbsp;";
                                endif;
                                echo("</div>");
                            endif;
                        endif; ?>
                </div>
                <!-- Flip card -->
                    <?php
                    if (in_array($row['layout'], $rulesTwoCardDetailSections)) : ?>
                    <div id="carddetailflip">
                        <div id="carddetailflipimg">
                            <table>
                               <tr>
                                    <td colspan="2">
                                            <?php
                                            $lookupid = htmlentities($row['cs_id'], ENT_QUOTES, "UTF-8");
                                            $imagelocationback = $imagebackurl;

                                            $msg->logMessage('[DEBUG]', "Image location is " . $imagelocationback);
                                            ?>
                                            <div class='backimgfloat' id='image-<?php echo $row['cs_id'];?>'>
                                                <img
                                                    class='backimg'
                                                    data-cardid="<?php echo $row['cs_id']; ?>"
                                                    data-face="back"
                                                    alt='<?php echo $imagelocationback;?>'
                                                    src='<?php echo $imagelocationback;?>'
                                                >
                                            </div>
                                        <?php
                                        if (isset($row['multiverse2'])) :
                                            $multiverse_id_2 = $row['multiverse2'];
                                            echo "<a href='https://gatherer.wizards.com/Pages/Card/Details.aspx?"
                                                . "multiverseid=" . $multiverse_id_2
                                                . "' target='_blank'><img alt='$lookupid' id='cardimg' "
                                                . "class='backimg' data-cardid='{$row['cs_id']}' data-face='back' "
                                                . "src=$imagelocationback></a>";
                                        elseif (isset($row['scryfall_uri']) and $row['scryfall_uri'] !== "") :
                                                echo "<a href='" . $row['scryfall_uri'] . "' target='_blank'><img "
                                                    . "alt='$lookupid' id='cardimg' class='backimg' "
                                                    . "data-cardid='{$row['cs_id']}' data-face='back' "
                                                    . "src=$imagelocationback></a>";
                                        else :
                                                echo "<a href='https://gatherer.wizards.com/' target='_blank'><img "
                                                    . "alt='$lookupid' id='cardimg' class='backimg' "
                                                    . "data-cardid='{$row['cs_id']}' data-face='back' "
                                                    . "src=$imagelocationback></a>";
                                        endif;

                                        ?>

                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div id="carddetailflipinfo">
                            <h3 class="shallowh3">Flip details</h3>
                                <?php
                                if (
                                    isset($row['f2_flavor_name'])
                                    and $row['f2_flavor_name'] !== null
                                    and $row['f2_flavor_name'] !== ''
                                ) :
                                    echo "<b>Name: </b>{$row['f2_flavor_name']} <i>({$row['f2_name']})</i>";
                                else :
                                    echo "<b>Name: </b>" . $row['f2_name'];
                                endif;
                                echo "<br>";
                                if (isset($row['f2_cmc']) and $row['f2_cmc'] !== null) :
                                    if (Validation::validateTrueDecimal($row['f2_cmc'], $appConfig) === false) :
                                        $msg->logMessage('[DEBUG]', "Trying to round f2_cmc {$row['f2_cmc']}");
                                        $row['f2_cmc'] = round($row['f2_cmc']);
                                        echo "<b>Mana value: </b>" . $row['f2_cmc'];
                                        echo "<br>";
                                    elseif (Validation::validateTrueDecimal($row['f2_cmc'], $appConfig) === true) :
                                        echo "<b>Mana value: </b>" . $row['f2_cmc'];
                                        echo "<br>";
                                    endif;
                                endif;
                                $flipmanacost = CardUtils::symbolReplaceFont($row['f2_manacost']);
                                if ($flipmanacost !== null and $flipmanacost !== '') :
                                    echo "<b>Mana cost: </b>" . $flipmanacost;
                                    echo "<br>";
                                endif;
                                if (isset($row['f2_type']) and $row['f2_type'] !== null and $row['f2_type'] != '') :
                                    echo "<b>Type: </b>" . $row['f2_type'];
                                    echo "<br>";
                                endif;
                                if (isset($card_lang) and $card_lang != '' and $card_lang != 'en') :
                                    echo "<b>Lang: </b>" . $gameRules->getLanguageLabel($card_lang);
                                    echo "<br>";
                                endif;
                                if (isset($flipability) and $flipability !== null and $flipability != '') :
                                    $flipabilityText = $flipability;
                                    if (
                                        $row['f2_type'] !== null
                                        and strpos($row['f2_type'], 'laneswalker') !== false
                                    ) :
                                        $flipabilityText = CardUtils::planeswalkerLoyaltyReplace(
                                            $flipabilityText,
                                            $msg
                                        );
                                    endif;
                                    $flipabilityText = CardUtils::symbolReplaceFont($flipabilityText);
                                    echo "<b>Abilities: </b>" . $flipabilityText;
                                    echo "<br>";
                                endif;
                                if ($row['f2_type'] !== null and strpos($row['f2_type'], 'reature') !== false) :
                                    echo "<b>Power / Toughness: </b>" . $row['f1_power'] . "/" . $row['f2_toughness'];
                                    echo "<br>";
                                elseif ($row['f2_type'] !== null and strpos($row['f2_type'], 'laneswalker') !== false) :
                                    echo "<b>Loyalty: </b>" . $row['f2_loyalty'];
                                    echo "<br>";
                                endif;
                                if ($row['f2_artist'] !== null and $row['f2_artist'] != '') :
                                    echo "<b>Art by: </b>" . $row['f2_artist'] . "<br>";
                                endif;
                                ?>
                        </div>
                    </div>
                    <div id="flipcarddetailrulings">
                            <?php
                            if ($ruling === '') :
                                echo "<h3 class='shallowh3'>Rulings</h3>";
                                echo "None";
                            else :
                                echo "<h3 class='shallowh3'>Rulings</h3> " . $ruling . "&nbsp;";
                            endif; ?>
                    </div> <?php
                    endif;
                    ?>
        <!-- Disqus -->
                <?php
                if ($disqus === 1) :
                    $msg->logMessage('[DEBUG]', "Disqus enabled");
                    $page_url = strtok(UrlHelper::getFullUrl(), '?') . "?id=" . $cardId;
                    if ($tier === 'dev') :
                        $msg->logMessage('[DEBUG]', "Disqus site is '$disqusDev'");
                        $disqus_site = "$disqusDev/embed.js";
                    else :
                        $msg->logMessage('[DEBUG]', "Disqus site is '$disqusProd'");
                        $disqus_site = "$disqusProd/embed.js";
                    endif;
                    ?>
                    <div id="disqus_thread"></div>
                        <script>
                            var disqus_config = function () {
                            this.page.url = '<?php echo $page_url;?>';
                            this.page.identifier = '<?php echo $page_url;?>';
                            this.page.title = '<?php echo $row['name'] . " - " . $setname;?>';
                            };
                            (function() {
                            var d = document, s = d.createElement('script');
                            s.src = '<?php echo $disqus_site;?>';
                            s.setAttribute('data-timestamp', +new Date());
                            (d.head || d.body).appendChild(s);
                            })();
                        </script>
                        <noscript>
                            Please enable JavaScript to view the
                            <a href="https://disqus.com/?ref_noscript">comments powered by Disqus.</a>
                        </noscript>
                    <?php
                else :
                    $msg->logMessage('[DEBUG]', "Disqus not enabled, skipping");
                endif;
        else :
                echo 'No such card, check the details.';
        endif;
    else :
        echo '<h3>Error</h3>Valid card ID not supplied';
    endif;
    ?>
    </div>
</div>
<?php
if (isset($ctrlf5)) :
    echo "<meta http-equiv='refresh' content='0;url=carddetail.php?id=$cardId'>";
endif;
?>
<script>
    window.mtgCardDetailConfig = {
        cardId: <?php echo json_encode($cardId);?>,
        lookupId: <?php echo json_encode(isset($lookupid) ? $lookupid : ''); ?>
    };
</script>
<script src="/js/carddetail.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
<?php
require APP_ROOT . '/includes/footer.php'; ?>
</body>
</html>
