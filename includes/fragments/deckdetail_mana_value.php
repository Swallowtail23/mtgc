<?php

/*
Version:     1.2
Date:        26/12/25
Name:        deckdetail_mana_value.php
Purpose:     Deck detail mana value chart fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
?>
<?php
$hasManaValue = $show_mana_block;
$cmcCounts = $cmc[0] . ',' . $cmc[1] . ',' . $cmc[2] . ',' . $cmc[3] . ',' . $cmc[4] . ',' . $cmc[5]
    . ',' . $cmc[6];
?>
<div
    id="deck-mana-value-fragment"
    data-has-content="<?php echo $hasManaValue ? '1' : '0'; ?>"
    data-show-chart="<?php echo $show_mana_block ? '1' : '0'; ?>"
    data-cmc-counts="[<?php echo $cmcCounts; ?>]">
    <?php
    if ($hasManaValue) :
        ?>
        <h4>Mana value</h4>
        <div id="barchart_material" style="width: 85%; height: 150px;"></div>
        <?php
        if ($avgcmc !== null) :
            echo "<br>Average mana value = $avgcmc";
        else :
            echo "<br>Average mana value = N/A";
        endif;
    endif;
    ?>
</div>
