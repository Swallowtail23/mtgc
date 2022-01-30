<?php 
/* Version:     3.0
    Date:       28/01/2022
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
 *  3.0
 *              Refactoring for cards_scry
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
        $stmt = $db->prepare("SELECT 
                                set_name,
                                setcode,
                                min(release_date) as date
                            FROM 
                                cards_scry 
                            GROUP BY 
                                set_name
                            ORDER BY 
                                date DESC");
        if ($stmt === false):
            trigger_error("[ERROR] ".basename(__FILE__)." ".__LINE__,": Preparing SQL: " . $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error("[ERROR] ".basename(__FILE__)." ".__LINE__,": Executing SQL: " . $db->error, E_USER_ERROR);
        else: 
            $result = $stmt->get_result();
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,$result->num_rows." results",$logfile);
            if ($result->num_rows === 0):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__.": No results ". $db->error, E_USER_ERROR);
            elseif($result->num_rows > 0):
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,$stmt->num_rows." results",$logfile);
            endif;
        endif;
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
            if($result === false):
                // Should never get here with catches above
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Error retrieving data",$logfile); ?>
                <tr>
                    <td colspan="2">Error retrieving data</td>
                </tr> <?php
            else:
                while ($row = $result->fetch_assoc()): 
                    $setcodeupper = strtoupper($row['setcode']);?>
                    <tr>
                        <td>
                            <?php echo "<a href='index.php?adv=yes&amp;searchname=yes&amp;legal=any&amp;set%5B%5D=$setcodeupper&amp;sortBy=setdown&amp;layout=grid'>$setcodeupper</a>"; ?>
                        </td>
                        <td>
                            <?php echo $row['set_name']; ?>
                        </td>
                    </tr>
                    <?php 
                endwhile;
            endif;
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