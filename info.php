<?php

/*
Version:     2.8
Date:        10/01/26
Name:        info.php
Purpose:     Site information page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php'; // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php'; // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php'; // Setup page variables
SessionManager::forcePasswordChange($appConfig); // Check if user is disabled or needs to change password
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - info</title>
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body id="body" class="body">
<?php
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php');
require('includes/menu.php');

?>
<div id='page'>
    <div class='staticpagecontent'>
        <div id="printtitle" class="headername">
            <img src="images/white_m.png"><?php echo $siteTitleEsc;?>
        </div>
        <h2 class='h2pad'>Copyright</h2>
        Website design &copy; <?php echo $copyright;?><br>
        The information presented on this site about Magic: The Gathering is copyrighted by Wizards of the Coast.<br>
        This website is not produced, endorsed, supported, or affiliated with Wizards of the Coast.<br>
        Thanks to Andrew Gioia for his Keyrune project (<a target='_blank'
            href='https://keyrune.andrewgioia.com/'>https://keyrune.andrewgioia.com/</a>)<br><br>
        <h2 id='h2'>Privacy and security</h2>
        This app stores the following information:
        <ul>
            <li>Your email address, used to log on</li>
            <li>Your password, securely encrypted (salted and hashed)</li>
            <li>Information about any cards you may add to "My Collection"</li>
            <li>Your IP address used to access this site</li>
        </ul>
        If you want to completely delete your account <a href='help.php'>send me a request</a> and I will delete all
        stored information.<br>
    <hr class="styled">
    <h3 class="shallowh3">Updates</h3>

    <?php
    $date = null;
    $result = $db->execute_query('SELECT `date`,`update`,`author` FROM updatenotices ORDER by date DESC');
    if (($result === false) or ($result === null)) :
        throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
    else :
        while ($row = $result->fetch_assoc()) :
            $updateText = htmlspecialchars($row['update'] ?? '', ENT_NOQUOTES, 'UTF-8');
            if (!isset($date)) :
                $date = $row['date'];
                $formatteddate = date_format(new DateTime($date), "d F Y");
                echo "<b>" . $formatteddate . "</b><br><ul>";
                echo "<li>" . $updateText . "</li>";
            elseif ($row['date'] != $date) :
                $date = $row['date'];
                $formatteddate = date_format(new DateTime($date), "d F Y");
                echo "</ul><b>" . $formatteddate . "</b><br><ul>";
                echo "<li>" . $updateText . "</li>";
            else :
                echo "<li>" . $updateText . "</li>";
            endif;
        endwhile;
    endif;
    echo "</ul>";
    ?>

&nbsp;
</div>
</div>
<?php
require('includes/footer.php');
?>
</body>
</html>
