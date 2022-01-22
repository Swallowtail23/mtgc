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

session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password
?> 
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title>MtG collection info</title>
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
            <img src="images/white_m.png">MtG collection
        </div>
        <h2 class='h2pad'>Known issues and bugs</h2>
    <ul>
        <li><b>The scrolling results page never goes past the first page.</b> Turn off AdBlock for the site. No idea why this works, but it does.</li>
        <li><b>Searching for "+1/+1" or "-1/-1" fails to load beyond the first page of results.</b>
            This is a bug with the Infinite Ajax Scroll script, and so far I have been unable to resolve it.</li>
        <li><b>No way to add, remove or interact with Groups.</b> A bit of work in this, it's on the list.</li>
        <li><b>The type search does an OR search.</b> Correct, workaround for now is to use fuzzy type search. AND / OR is on the list.</li>
        <li><b>Image for card {xyz} looks poor quality.</b> High definition images are not available for all cards. Let me know and I will check.</li>
    </ul>
&nbsp;
</div>
</div>
<?php 
require('includes/footer.php'); 
?>
</body>
</html>