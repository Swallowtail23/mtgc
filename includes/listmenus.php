<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       listmenus.php
    Purpose:    PHP script to display menus of index.php results on list page
    Notes:      {none}
 * 
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id="gridcmd" class='gridlist fullsize'>
    <?php 
    $listtogrid = 1;
    echo "<a href='/index.php{$getstringbulk}&amp;layout=grid&amp;page={$listtogrid}'>GRID</a>"; ?>
</div>

<div id='listcmd' class='activegridlist fullsize'>
    LIST
</div>

<div id='bulkcmd' class='gridlist fullsize'>
    <?php echo "<a href='/index.php".$getstringbulk."&amp;layout=bulk'>BULK</a>"; ?>
</div>

<div id='results-header'>
    <table>
        <tr>
            <td id="colname">Name
            </td>
            <td id="colrarity">Rarity
            </td>
            <td id="colset">Set
            </td>
            <td id="coltype">Type
            </td>
            <td id="colnumber">Number
            </td>
            <td id="colmana">Mana Cost
            </td>
            <td id="colcollection">My cards
            </td>
            <td id="colabilities">Abilities
            </td>
        </tr>
    </table>
</div>