<?php

/*
Version:     1.2
Date:        28/04/26
Name:        deckdetail_export_list.php
Purpose:     Deck detail export list fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$total = $total ?? 0;
$sidetotal = $sidetotal ?? 0;
$deckName = $deckName ?? '';
$deckNumber = $deckNumber ?? 0;

?>
<tbody id="deck-export-fragment">
    <?php
    if ($total + $sidetotal > 0) :
        $filename = preg_replace('/[^\w]/', '', $deckName);
        ?>
        <tr style="height:36px;">
            <td>Export formatted card list:</td>
            <td>
                <form action="dltext.php" method="POST">
                    <input class='profilebutton' type="submit" value="DECKLIST">
                    <?php echo "<input type='hidden' name='decknumber' value='$deckNumber'>"; ?>
                </form>
            </td>
        </tr>
        <?php
    endif;
    ?>
</tbody>
