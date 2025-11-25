<?php
/*
Version:     1.2
Date:        25/11/25
Name:        adminmenus.php
Purpose:     Menus for admin pages
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection

History:
    1.0 18/10/16 Initial version
    1.1 24/11/25 PHPCS cleaned
    1.2 25/11/25 Header tidy and metadata standardization
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

if ($_SERVER['PHP_SELF'] == "/admin/admin.php") :
    ?>
    <div id="adminsite" class='activegridlist fullsize'>
        SITE
    </div>

    <div id='adminusers' class='gridlist fullsize'>
        <a href='/admin/users.php'>USERS</a>
    </div>

    <div id='admincards' class='gridlist fullsize'>
        <a href='/admin/cards.php'>CARDS</a>
    </div>
    <?php
elseif ($_SERVER['PHP_SELF'] == "/admin/users.php") :
    ?>
    <div id="adminsite" class='gridlist fullsize'>
        <a href='/admin/admin.php'>SITE</a>
    </div>

    <div id='adminusers' class='activegridlist fullsize'>
        USERS
    </div>

    <div id='admincards' class='gridlist fullsize'>
        <a href='/admin/cards.php'>CARDS</a>
    </div>
    <?php
elseif ($_SERVER['PHP_SELF'] == "/admin/sets.php") :
    ?>
    <div id="adminsite" class='gridlist fullsize'>
        <a href='/admin/admin.php'>SITE</a>
    </div>

    <div id='adminusers' class='gridlist fullsize'>
        <a href='/admin/users.php'>USERS</a>
    </div>

    <div id='admincards' class='gridlist fullsize'>
        <a href='/admin/cards.php'>CARDS</a>
    </div>
    <?php
elseif ($_SERVER['PHP_SELF'] == "/admin/cards.php") :
    ?>
    <div id="adminsite" class='gridlist fullsize'>
        <a href='/admin/admin.php'>SITE</a>
    </div>

    <div id='adminusers' class='gridlist fullsize'>
        <a href='/admin/users.php'>USERS</a>
    </div>

    <div id='admincards' class='activegridlist fullsize'>
        CARDS
    </div>
    <?php
endif;
?>
