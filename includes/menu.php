<?php 
/* Version:     2.025/03/23
    Name:       menu.php
    Purpose:    PHP script to display menu
    Notes:      {none}
 * 
    1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id='menubuttondiv' class="togglemenu">    
    <a href="#" id='toggle-menu'></a>
</div>
<div id="menu">
    <div class='nav_profile'>
        <a id='profile_cell' title="Profile" href="/profile.php">Profile</a>
        <a id="nav_email" href="/profile.php"><?php echo $useremail; ?></a>
    </div>
    <div class='nav_nodivider'><a title="Home" href="/">Home</a></div>
    <div class='nav_nodivider'><a title="Decks" href="/sets.php">Sets</a></div>
    <div class='nav_nodivider'><a title="Decks" href="/decks.php">Decks</a></div>
    <div class='nav_nodivider'><a title="About" href="/info.php">About</a>
        <?php
        
        //If Update notice within last week, display NEW on menu
        if(isset($db)):
            if($row = $db->select_one('date','updatenotices',"ORDER by date DESC")):
                $latestupdate = strtotime($row['date']);
                if((time()-(60*60*24*7)) < $latestupdate):
                    ?>
                    <div id='newcontent' display=block>
                        <a href='info.php'><span></span></a>
                        <div id='newlabel'>
                            <a href='info.php'><span>NEW</span></a>
                        </div>
                    </div>
                    <?php
                endif;
            else:
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"No menu updates",$logfile);
            endif;
        endif;
        ?>
    </div>
    <div class='nav_divider'><a title="Help" href="/help.php">Help</a></div>
    <?php if (isset($_SESSION['admin']) AND ($_SESSION['admin'] === TRUE)): ?>
        <div class='nav_divider'><a title="Admin" href="/admin/admin.php">Admin</a></div>
    <?php endif; ?>
</div>