<?php

/*
Version:     1.3
Date:        29/12/25
Name:        template.php
Purpose:     Site template.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php';                // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php';          // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php';       // Setup page variables
forcePasswordChange();
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - template</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
    >
    <?php include 'includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
include_once 'includes/analyticstracking.php';
require 'includes/overlays.php';
require 'includes/header.php';
require 'includes/menu.php';
?>
<div id="page">
    <div class="staticpagecontent">
        <h2 id="h2">Template title</h2>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
