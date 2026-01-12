<?php

/*
Version:     3.58
Date:        12/01/26
Name:        ajaxrandomdraw.php
Purpose:     PHP script to generate random hand draws for decks
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;

if (defined('INCLUDE_CHECK') && INCLUDE_CHECK === true) :
    if (!isset($uniquecard_ref) || !is_array($uniquecard_ref)) :
        $uniquecard_ref = [];
    endif;
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') :
    // Bootstrap

    $appContext = require dirname(__DIR__) . '/bootstrap.php';

    $myURL = (string) $appConfig->general('url', '');

    $rulesImage90Rotate = $gameRules->getArray('image90rotate');

    $expectedReferringPages = [
        $myURL . '/deckdetail.php'
    ];
    $ajaxValidation = SessionManager::validateAjaxRequest(
        $expectedReferringPages,
        $appConfig,
        'ajaxrandomdraw.php',
        false
    );
    if ($ajaxValidation['valid'] === false) :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo 'Access forbidden';
        exit();
    endif;

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
        exit();
    else :
        // Decode the JSON data received from the POST request
        $data = json_decode(file_get_contents('php://input'), true);
        $csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';
        if (!SessionManager::validateCsrfToken($csrfToken)) :
            exit();
        endif;

        // Check if the required variables are present
        if (
            isset($data['uniquecard_ref'])
            && isset($data['include_check'])
            && $data['include_check'] === true
        ) :
            $uniquecard_ref = $data['uniquecard_ref'];
            $msg->logMessage('[DEBUG]', print_r($uniquecard_ref, true));
        else :
            exit;
        endif;
    endif;
else :
    die('Direct access prohibited');
endif;

if (!is_array($uniquecard_ref)) :
    $uniquecard_ref = [];
endif;

$filtered_refs = [];
foreach ($uniquecard_ref as $entry) :
    if (!is_array($entry)) :
        continue;
    endif;
    $filtered_refs[] = $entry;
endforeach;

$uniquecard_ref = $filtered_refs;
if (count($uniquecard_ref) < 7) :
    if (!defined('INCLUDE_CHECK') || INCLUDE_CHECK !== true) :
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    endif;
    echo "<div class='msg-new error-new'><span>Not enough cards for a random draw</span></div>";
    exit();
endif;

$a = array_rand($uniquecard_ref, 7);
$drawn_cards = [];
$allowedHoverClasses = ['randomcardimgdiv', 'randomcardimgdiv splitfloat'];
$allowedCardClasses = ['random-draw-card', 'random-draw-card is-rotated'];
for ($i = 0; $i < 7; $i++) :
    $cardurl = $uniquecard_ref[$a[$i]]['cardurl'] ?? '';
    if (
        !is_string($cardurl)
        || !preg_match(
            '#^/carddetail\.php\?id=[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#i',
            $cardurl
        )
    ) :
        $cardurl = '#';
    endif;
    $imgurl = $uniquecard_ref[$a[$i]]['imageurl'] ?? '/images/back.jpg';
    if (
        !is_string($imgurl)
        || !preg_match('#^/?(images|cardimg)/[A-Za-z0-9/_\.-]+$#', $imgurl)
    ) :
        $imgurl = '/images/back.jpg';
    endif;
    $name = $uniquecard_ref[$a[$i]]['name'] ?? '';
    $id = $uniquecard_ref[$a[$i]]['cardid'] ?? '';
    $layout = $uniquecard_ref[$a[$i]]['layout'] ?? null;
    $f1_type = $uniquecard_ref[$a[$i]]['f1_type'] ?? null;
    $randomref = $i + 1;
    if (
        (isset($layout) and in_array($layout, $rulesImage90Rotate))
        or (isset($f1_type) and in_array($f1_type, $rulesImage90Rotate))
    ) :
        $hoverclass = 'randomcardimgdiv splitfloat';
        $is_rotated = true;
    else :
        $hoverclass = 'randomcardimgdiv';
        $is_rotated = false;
    endif;
    if (!in_array($hoverclass, $allowedHoverClasses, true)) :
        $hoverclass = 'randomcardimgdiv';
    endif;
    $cardClass = $is_rotated ? 'random-draw-card is-rotated' : 'random-draw-card';
    if (!in_array($cardClass, $allowedCardClasses, true)) :
        $cardClass = 'random-draw-card';
    endif;
    $drawn_cards[] = [
        'randomref' => $randomref,
        'hoverclass' => $hoverclass,
        'isRotated' => $is_rotated,
        'cardClass' => $cardClass,
        'safeName' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'safeId' => htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'safeUrl' => htmlspecialchars($cardurl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'safeImg' => htmlspecialchars($imgurl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    ];
endfor;
if (isset($msg)) :
    $msg->logMessage('[DEBUG]', 'Random draw generated.');
endif;

echo "<div class='random-draw-content'>";
echo "<div class='random-draw-strip' aria-label='Random draw cards'>";
foreach ($drawn_cards as $index => $card) :
    echo "<a class='{$card['cardClass']}' href='{$card['safeUrl']}' style='--random-index: {$index};'>"
        . "<img class='random-draw-card-img' alt='{$card['safeName']}' src='{$card['safeImg']}'>"
        . "</a>";
endforeach;
echo "</div>";
echo "<div class='random-draw-list'>";
foreach ($drawn_cards as $card) : ?>
    <div class='<?php echo $card['hoverclass']; ?>' id='<?php echo "random-{$card['randomref']}"; ?>'>
        <a href='<?php echo $card['safeUrl']; ?>'>
        <img
            alt='<?php echo $card['safeName']; ?>'
            class='deckcardimg'
            data-cardid="<?php echo $card['safeId']; ?>"
            data-front-src="<?php echo $card['safeImg']; ?>"
            src='<?php echo $card['safeImg']; ?>'
        ></a>
    </div>
<?php endforeach;
echo "<table>";
foreach ($drawn_cards as $card) :
    echo "<tr><td class='hoverTD'>{$card['randomref']}: "
        . "<a class='taphover' id='random-{$card['randomref']}-taphover' "
        . "data-cardid='{$card['safeId']}' href='{$card['safeUrl']}'>"
        . "{$card['safeName']}</a></td></tr>";
endforeach;
echo "</table>";
echo "</div>";
echo "</div>";
