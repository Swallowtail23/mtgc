<?php

/*
Version:     1.42
Date:        28/04/26
Name:        deckdetail_random_draw.php
Purpose:     Deck detail random draw fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$decktype = $decktype ?? '';
$random_draw_refs = isset($uniquecard_ref) ? $uniquecard_ref : [];
$random_draw_enabled = isset($uniquecard_ref) && count($uniquecard_ref) > 6 && $decktype != 'Wishlist';
$hasRandomDraw = $random_draw_enabled;
$random_draw_refs_json = htmlspecialchars(json_encode($random_draw_refs), ENT_QUOTES, 'UTF-8');
$uniquecard_ref = $random_draw_refs;
?>
<div
    id="deck-random-draw-fragment"
    data-has-content="<?php echo $hasRandomDraw ? '1' : '0'; ?>"
    data-enabled="<?php echo $random_draw_enabled ? '1' : '0'; ?>"
    data-refs="<?php echo $random_draw_refs_json; ?>">
    <?php if ($hasRandomDraw) : ?>
        <div class="random-draw-header">
            <h4>Random draw</h4>
            <button class="random-draw-refresh material-symbols-outlined" id="random-draw-button" type="button"
                title="New draw">refresh</button>
        </div>
        <div id="table-container">
            <?php
            define('INCLUDE_CHECK', true);
            include APP_ROOT . '/ajax/ajaxrandomdraw.php'; ?>
        </div>
    <?php endif; ?>
</div>
