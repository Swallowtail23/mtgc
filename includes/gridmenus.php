<?php

/*
Version:     1.2
Date:        28/04/26
Name:        gridmenus.php
Purpose:     PHP script to display menus of index.php results on grid page
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$getstringbulk = $getstringbulk ?? '';
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
    <?php echo "<a href='/index.php" . $getstringbulk . "&amp;layout=bulk'>BULK</a>"; ?>
</div>
