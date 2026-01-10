<?php

/*
Version:     1.1
Date:        24/12/25
Name:        deckdetail_missing.php
Purpose:     Deck detail missing list fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

?>
<tbody id="deck-missing-fragment">
    <?php
    if ($total + $sidetotal > 0) :
        if ($requiredlist != '') :
            $requiredlist = htmlspecialchars($requiredlist, ENT_QUOTES, 'UTF-8');
            $requiredbuy = htmlspecialchars($requiredbuy, ENT_QUOTES, 'UTF-8');
            $filename_missing = preg_replace('/[^\w]/', '', $deckName . '_missing');
            $msg->logMessage('[DEBUG]', "Required list = $requiredlist");
            $msg->logMessage('[DEBUG]', "Missing filename = $filename_missing");
            ?>
            <script type="text/javascript">
                document.body.style.cursor='default';
            </script>
            <tr style="height:36px;">
                <td>Missing from My Collection:</td>
                <td>
                    <form action="dltext.php" method="POST">
                        <input class='profilebutton' type="submit" value="MISSING">
                        <?php echo "<input type='hidden' name='text' value='$requiredlist'>"; ?>
                        <?php echo "<input type='hidden' name='filename' value='$filename_missing'>"; ?>
                    </form>
                </td>
            </tr>
            <?php
        else : ?>
            <tr style="height:48px;">
                <td colspan="2">(No cards missing from My Collection)</td>
            </tr>
            <?php
        endif;
    endif;
    ?>
</tbody>
