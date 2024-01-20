<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       template.php
    Purpose:    site template
    Notes:      {none} 
        
    1.0
                Initial version
*/
require ('includes/sessionname.php');
startCustomSession();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');      //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
forcechgpwd();   
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title> MtG collection - template</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
// Includes first - menu and header            
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php'); 
require('includes/menu.php');
// Next the main DIV section


?>
<div id="page">
    <div class="staticpagecontent">
        <h2 id="h2">Template title</h2>
    </div>
</div>

<?php require('includes/footer.php'); ?>        
</body>
</html>
