<?php

/*
Version:     1.2
Date:        28/04/26
Name:        bulkmenus.php
Purpose:     PHP script to display menus of index.php results on bulk page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$getstringbulk = $getstringbulk ?? '';
?>

<div id="gridcmd" class='gridlist fullsize'>
    <?php echo "<a href='/index.php" . $getstringbulk . "&amp;layout=grid'>GRID</a>"; ?>
</div>

<div id='listcmd' class='gridlist fullsize'>
    <?php echo "<a href='/index.php" . $getstringbulk . "&amp;layout=list'>LIST</a>"; ?>
</div>

<div id='bulkcmd' class='activegridlist fullsize'>
    BULK
</div>
