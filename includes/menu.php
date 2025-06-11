<?php 
/* Version:     2.1
 *              25/03/23
    Name:       menu.php
    Purpose:    PHP script to display menu
    Notes:      {none}
 * 
    1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
 * 
 *  2.1         20/01/24
 *              Move to logMessage
 * 
 *  3.0         12/06/25
 *              Fixed for if there are no update notices 
*/

if (__FILE__ === $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id='menubuttondiv' class="togglemenu">    
    <a href="#" id='toggle-menu'><span id ="menu-icon" class="material-symbols-outlined menu">menu</span></a>
</div>
<div id="menu">
    <div class='nav_profile'>
        <a id='profile_cell' title="Profile" href="/profile.php">Profile</a>
        <a id="nav_email" href="/profile.php"><?php echo htmlspecialchars($useremail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></a>
    </div>
    <div class='nav_nodivider'><a title="Home" href="/">Home</a></div>
    <div class='nav_nodivider'><a title="Sets" href="/sets.php">Sets</a></div>
    <div class='nav_nodivider'><a title="Decks" href="/decks.php">Decks</a></div>
    <div class='nav_nodivider'><a title="About" href="/info.php">About</a>
        <?php
        
        //If Update notice within last week, display NEW on menu
    // If Update notice within last week, display NEW on menu
    if (isset($db)):

        // Prepared-statement style keeps it tidy
        $rowqry = $db->execute_query(
            "SELECT `date` FROM `updatenotices` ORDER BY `date` DESC LIMIT 1"
        );

        if ($rowqry):                                // query succeeded
            $row = $rowqry->fetch_assoc();           // may be null if 0 rows

            if ($row !== null
                AND array_key_exists('date', $row)
                AND $row['date'] !== null):

                $latestupdate = strtotime($row['date']);

                if ((time() - (60 * 60 * 24 * 7)) < $latestupdate):
                    ?>
                    <div id="newcontent">
                        <a href="info.php"><span></span></a>
                        <div id="newlabel">
                            <a href="info.php"><span>NEW</span></a>
                        </div>
                    </div>
                    <?php
                endif;

            else:   // no rows yet, or NULL date
                $msg->logMessage('[DEBUG]','menu.php: no updatenotices rows found or NULL date');
            endif;

        else:       // SQL error
            $msg->logMessage('[ERROR]','menu.php: updatenotices query failed – ' . $db->error);
        endif;
    endif; ?>
    </div>
    <div class='nav_divider'><a title="Help" href="/help.php">Help</a></div>
    <?php if (isset($_SESSION['admin']) AND ($_SESSION['admin'] === TRUE)): ?>
        <div class='nav_divider'><a title="Admin" href="/admin/admin.php">Admin</a></div>
    <?php endif; ?>
</div>
