<?php 
/* Version:     4.0
    Date:       02/12/2023
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
 *
 *  4.0         02/12/2023
 *              Add pagination and set image reload for admins
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
    <title>MtG collection sets</title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
    <script>
        function reloadImages(setcode) {
            $.ajax({
                type: 'POST',
                url: 'admin/ajaxsetimg.php',
                data: { setcode: setcode },
                success: function(response) {
                    // Handle the response if needed
                    console.log(response);
                },
                error: function(error) {
                    // Handle errors if needed
                    console.error(error);
                }
            });
        }
    </script>
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
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $setsPerPage = 50; // Adjust this value based on your preference
        $offset = ($page - 1) * $setsPerPage;
        $stmt = $db->prepare("SELECT 
                                set_name,
                                setcode,
                                parent_set_code,
                                set_type,
                                card_count,
                                nonfoil_only,
                                foil_only,
                                min(cards_scry.release_date) as date
                            FROM cards_scry 
                            LEFT JOIN sets ON cards_scry.set_id = sets.id
                            GROUP BY 
                                set_name
                            ORDER BY 
                                date DESC 
                            LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $setsPerPage, $offset);
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
            endif;
        endif;
        ?>
        <h2 class='h2pad'>Sets Information</h2>
        <table id='setlist'>
            <tr>
                <td>
                    <b>Set icon</b>
                </td>
                <td>
                    <b>Set code</b>
                </td>
                <td>
                    <b>Set name</b>
                </td>
                <td>
                    <b>Set type</b>
                </td>
                <td>
                    <b>Parent set</b>
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
                    if(isset($row['setcode']) AND $row['setcode'] !== null):
                        $setcodeupper = strtoupper($row['setcode']);
                    else:
                        $setcodeupper = '';
                    endif;
                    if(isset($row['set_name']) AND $row['set_name'] !== null):
                        $setname = $row['set_name'];
                    else:
                        $setname = '';
                    endif;
                    if(isset($row['set_type']) AND $row['set_type'] !== null):
                        $settype = ucfirst($row['set_type']);
                    else:
                        $settype = '';
                    endif;
                    if(isset($row['parent_set_code']) AND $row['parent_set_code'] !== null):
                        $parentsetcode = strtoupper($row['parent_set_code']);
                    else:
                        $parentsetcode = '';
                    endif;
                    ?>
                    <tr>
                        <td>
                            <?php 
                            $time = time();
                            echo "<img class='seticon' src='cardimg/seticons/{$row['setcode']}.svg?$time' alt='$setcodeupper'>"; 
                            //echo "<i><i class='ss ss-{$row['parent_set_code']} ss-grad ss-2x'></i>"; ?>
                        </td>
                        <td>
                            <?php echo "<a href='index.php?adv=yes&amp;searchname=yes&amp;legal=any&amp;set%5B%5D=$setcodeupper&amp;sortBy=setdown&amp;layout=grid'>$setcodeupper</a>"; ?>
                        </td>
                        <td>
                            <?php echo $setname.($admin == 1 ? ' <a href="javascript:void(0);" onclick="reloadImages(\''.$row['setcode'].'\')"><span class="material-symbols-outlined">frame_reload</span></a>' : ''); ?>
                        </td>
                        <td>
                            <?php echo $settype; ?>
                        </td>
                        <td>
                            <?php echo $parentsetcode; ?>
                        </td>
                    </tr>
                    <?php 
                endwhile;
            endif;
            ?>
        </table>
        <br>&nbsp; <?php
        $totalSetsQuery = $db->query("SELECT COUNT(DISTINCT set_name) as totalSets FROM cards_scry");
        $totalSets = $totalSetsQuery->fetch_assoc()['totalSets'];
        $totalPages = ceil($totalSets / $setsPerPage);

        echo '<div class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i == $page):
                    // Current page, display without hyperlink
                    echo '<span>' . $i . '&nbsp;&nbsp;</span>';
                else:
                    // Other pages, display with hyperlink
                    echo '<a href="?page=' . $i . '">' . $i . '&nbsp;&nbsp;</a>';
                endif;
            endfor;
        echo '</div>';
        ?>
    </div>
</div>
<?php     
    require('includes/footer.php'); 
?>
</body>
</html>