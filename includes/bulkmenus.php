<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       bulkmenus.php
    Purpose:    PHP script to display menus of index.php results on bulk page
    Notes:      {none}
        
    1.0
                Initial version
 */
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id="gridcmd" class='gridlist fullsize'>
    <?php echo "<a href='/index.php".$getstringbulk."&amp;layout=grid'>GRID</a>"; ?>
</div>

<div id='listcmd' class='gridlist fullsize'>
    <?php echo "<a href='/index.php".$getstringbulk."&amp;layout=list'>LIST</a>"; ?>
</div>

<div id='bulkcmd' class='activegridlist fullsize'>
    BULK
</div>