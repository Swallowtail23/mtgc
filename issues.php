<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       issues.php
    Purpose:    Issues page
    Notes:      No db functions. 
    To do:      -
    
    1.0
                Initial version
*/

if (file_exists('includes/sessionname.php')):
    require('includes/sessionname.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password
?> 
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitle;?> - issues</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
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
            <img src="images/white_m.png"><?php echo $siteTitle;?>
        </div>
        <h2 class='h2pad'>Known issues and bugs</h2>
    <ul>
        <li><b>Images are slow to load.</b> Images for new cards are fetched when they are added to the database, but for older cards the images are fetched the first time they are needed. This can take a while - be patient.</li>
        <li><b>The type search does an OR search.</b> Correct, workaround for now is to use fuzzy type search. AND / OR is on the list.</li>
    </ul>
&nbsp;
</div>
</div>
<?php 
require('includes/footer.php'); 
?>
</body>
</html>