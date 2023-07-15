<?php 
/* Version:     2.0
    Date:       17/10/16
    Name:       info.php
    Purpose:    Site information page
    Notes:      {None} 
    To do:      -
    
    1.0
                Initial version
    2.0         
                Mysqli_Manager migration completed.
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
        <h2 class='h2pad'>Copyright</h2>
        The information presented on this site about Magic: The Gathering is copyrighted by Wizards of the Coast.<br>
        This website is not produced, endorsed, supported, or affiliated with Wizards of the Coast.
        <h2 id='h2'>Privacy and security</h2>
        This app stores the following information:
        <ul>
            <li>Your email address, used to log on</li>
            <li>Your password, securely encrypted (salted and hashed)</li>
            <li>Information about any cards you may add to "My Collection"</li>
            <li>Your IP address used to access this site</li>
        </ul>
        If you want to completely delete your account <a href='help.php'>send me a request</a> and I will delete all stored information.<br><br>
        Website design &copy; <?php echo $copyright;?>
    <hr class="styled">
    <h3 class="shallowh3">Updates</h3>
    
    <?php
    $date = null;
    $result = $db->select('`date`, `update`, `author`','updatenotices','ORDER by date DESC');
    if(($result === false) OR ($result === null)):
        trigger_error('[ERROR] profile.php: Error: '.$db->error, E_USER_ERROR);
    else:
        while ($row = $result->fetch_assoc()):
            if(!isset($date)):
                $date = $row['date'];
                $formatteddate = date_format(new DateTime($date),"d F Y");
                echo "<b>".$formatteddate."</b><br><ul>";
                echo "<li>".$row['update']."</li>";
            elseif($row['date'] != $date):
                $date = $row['date'];
                $formatteddate = date_format(new DateTime($date),"d F Y");
                echo "</ul><b>".$formatteddate."</b><br><ul>";
                echo "<li>".$row['update']."</li>";
            else:
                echo "<li>".$row['update']."</li>";
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