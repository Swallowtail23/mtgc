<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       gridmenus.php
    Purpose:    PHP script to display menus of index.php results on grid page
    Notes:      {none}
 * 
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id="gridcmd" class='activegridlist fullsize'>
    GRID
</div>
<div id='listcmd' class='gridlist fullsize'>
    <?php 
    $gridtolist = 1;
    echo "<a href='/index.php{$getstringbulk}&amp;layout=list&amp;page=$gridtolist'>LIST</a>"; 
    ?>
</div>
<div id='bulkcmd' class='gridlist fullsize'>
    <?php echo "<a href='/index.php".$getstringbulk."&amp;layout=bulk'>BULK</a>"; ?>
</div>
