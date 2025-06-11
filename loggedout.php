<?php
/* Version:     1.0
    Date:       28/02/2025
    Name:       loggedout.php
    Purpose:    Hold file
    Notes:      {none}
    To do:      
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson

    1.0
                Initial version
 
 *  2.0         28/02/25
 *              Add trusted device handling
*/

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
// Load required files for database access
require_once('includes/ini.php');               //Initialise and load ini file
require_once('includes/error_handling.php');
require_once('includes/functions.php');         //Includes basic functions for non-secure pages

// Initialize message object for logging
$msg = new Message($logfile);

// Find CSS Version
$cssver = cssver();

?>

    <!DOCTYPE html>
        <head>
        <title> <?php echo $siteTitle;?> - logged out</title>
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
        <?php include('includes/googlefonts.php'); ?>
        <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
        </head>
        <body id="loginbody" class="body">
        <div id="loginheader">    
            <h2 id='h2'><?php echo $siteTitle;?></h2>
                You have been logged out. <a href="login.php">Click here to log back in</a>
        </div>
        </body>
    </html>
