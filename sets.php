<?php 
/* Version:     2.0
    Date:       17/10/16
    Name:       sets.php
    Purpose:    Lists all setcodes and sets in the database
    Notes:      This page is the only one NOT mobile responsive design. 
 *              This is because the only way to access it is from the
                link on profile.php, from a <div> that is not visible on mobile.
 *  To do:      -
        
    1.0
                Initial version
    2.0         
                Moved to use Mysqli_Manager library
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
    <title>MtG collection sets</title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
</head>

<body class="body">
<?php 
include_once("includes/analyticstracking.php");
require('includes/overlays.php');             
require('includes/header.php');
require('includes/menu.php'); 
?>
    
<div id='page'>
    <div class='staticpagecontent'>
        <?php 
        $sets = $db->select('setcodeid,fullsetname','sets');
        ?>
        <h2 class='h2pad'>Sets Information</h2>
        <table id='setlist'>
            <tr>
                <td>
                    <b>Set code</b>
                </td>
                <td>
                    <b>Set name</b>
                </td>
            </tr>
            <?php
            if($sets === false):
                trigger_error('[ERROR] sets.php: Wrong SQL: Error: ' . $db->error, E_USER_ERROR); ?>
                <tr>
                    <td colspan="2">Error retrieving data</td>
                </tr> <?php
            else:
                while ($row = $sets->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo "<a href='index.php?adv=yes&amp;searchname=yes&amp;legal=any&amp;set%5B%5D={$row['setcodeid']}&amp;sortBy=setdown&amp;layout=bulk'>{$row['setcodeid']}</a>"; ?>
                        </td>
                        <td>
                            <?php echo $row['fullsetname']; ?>
                        </td>
                    </tr>
                    <?php 
                endwhile;
            endif;
            $sets->close();
            ?>
        </table>
        <br>&nbsp; <?php
        ?>
    </div>
</div>
<?php     
    require('includes/footer.php'); 
?>
</body>
</html>