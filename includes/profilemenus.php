<?php

/*
Version:     1.1
Date:        07/12/25
Name:        profilemenus.php
Purpose:     Menus for profile/collection pages
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

if ($_SERVER['PHP_SELF'] == "/profile.php") :
    ?>
    <div id="profiletab" class='activegridlist fullsize'>
        PROFILE
    </div>

    <div id='collectiontab' class='gridlist fullsize'>
        <a href='/collection.php'>MY CARDS</a>
    </div>
    <?php
elseif ($_SERVER['PHP_SELF'] == "/collection.php") :
    ?>
    <div id="profiletab" class='gridlist fullsize'>
        <a href='/profile.php'>PROFILE</a>
    </div>

    <div id='collectiontab' class='activegridlist fullsize'>
        MY CARDS
    </div>
    <?php
endif;
?>
