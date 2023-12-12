<?php 
/* Version:     1.0
    Date:       18/10/16
    Name:       admin/adminmenus.php
    Purpose:    Menus for admin pages
    Notes:      
        
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

if ($_SERVER['PHP_SELF'] == "/admin/admin.php"):
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
elseif ($_SERVER['PHP_SELF'] == "/admin/users.php"):
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
elseif ($_SERVER['PHP_SELF'] == "/admin/sets.php"):
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
elseif ($_SERVER['PHP_SELF'] == "/admin/cards.php"):
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

    

