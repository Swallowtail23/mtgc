<?php
/*
Version:     1.8
Date:        24/12/25
Name:        ajaxrandomdraw.php
Purpose:     PHP script to generate random hand draws for decks
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (defined('INCLUDE_CHECK') && INCLUDE_CHECK === true) :
    if (!isset($uniquecard_ref)) :
        // do nothing
    endif;
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') :
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

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
        exit();
    else :
        // Decode the JSON data received from the POST request
        $data = json_decode(file_get_contents('php://input'), true);
        $csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';
        if (!validateCsrfToken($csrfToken)) :
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

$a = array_rand($uniquecard_ref, 7);
echo "<table>";
echo "<tr><td>&nbsp;</td></tr>";
for ($i = 0; $i < 7; $i++) {
    $cardurl = $uniquecard_ref[$a[$i]]['cardurl'];
    $imgurl = $uniquecard_ref[$a[$i]]['imageurl'];
    $name = $uniquecard_ref[$a[$i]]['name'];
    $id = $uniquecard_ref[$a[$i]]['cardid'];
    $cardref = $uniquecard_ref[$a[$i]]['cardref'];
    $layout = $uniquecard_ref[$a[$i]]['layout'] ?? null;
    $f1_type = $uniquecard_ref[$a[$i]]['f1_type'] ?? null;
    $randomref = $i + 1; ?>
    <?php
    if (
        (isset($layout) and in_array($layout, $image90rotate))
        or (isset($f1_type) and in_array($f1_type, $image90rotate))
    ) :
        $hoverclass = 'randomcardimgdiv splitfloat';
    else :
        $hoverclass = 'randomcardimgdiv';
    endif;
    ?>
    <div class='<?php echo $hoverclass; ?>' id='<?php echo "random-$randomref";?>'>
        <a href='<?php echo $cardurl;?>'>
        <img
            alt='<?php echo $name;?>'
            class='deckcardimg'
            data-cardid="<?php echo $id; ?>"
            data-front-src="<?php echo $imgurl; ?>"
            src='<?php echo $imgurl;?>'
        ></a>
    </div> <?php
    echo "<tr><td class='hoverTD'>$randomref: <a class='taphover' id='random-$randomref-taphover' "
        . "data-cardid='{$id}' href='$cardurl'>"
        . "$name</a></td></tr>";
}
echo "</table>";
