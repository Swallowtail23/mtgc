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
        <li><b>The scrolling results page never goes past the first page.</b> Turn off AdBlock for the site</li>
        <li><b>Images are slow to load.</b> Images are fetched the first time they are needed. This means for new cards, or not yet viewed cards, this can take a while - be patient.</li>
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