<?php

/*
Version:     1.2
Date:        24/12/25
Name:        deckdetail_random_draw.php
Purpose:     Deck detail random draw fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$random_draw_refs = isset($uniquecard_ref) ? $uniquecard_ref : [];
$random_draw_enabled = isset($uniquecard_ref) && count($uniquecard_ref) > 6 && $decktype != 'Wishlist';
$random_draw_refs_json = htmlspecialchars(json_encode($random_draw_refs), ENT_QUOTES, 'UTF-8');
$uniquecard_ref = $random_draw_refs;
?>
<div
    id="deck-random-draw-fragment"
    data-enabled="<?php echo $random_draw_enabled ? '1' : '0'; ?>"
    data-refs="<?php echo $random_draw_refs_json; ?>">
    <?php if ($random_draw_enabled) : ?>
        <h4>Random draw</h4>
        <button class='profilebutton' id="random-draw-button">NEW DRAW</button>
        <div id="table-container">
            <?php
            define('INCLUDE_CHECK', true);
            include __DIR__ . '/../../ajax/ajaxrandomdraw.php'; ?>
        </div>
    <?php endif; ?>
</div>
