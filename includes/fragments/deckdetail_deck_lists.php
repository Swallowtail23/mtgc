<?php

/*
Version:     1.31
Date:        26/12/25
Name:        deckdetail_deck_lists.php
Purpose:     Deck detail deck lists fragment wrapper.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$hasDeckLists = ($total + $sidetotal > 0);
?>
<div id="deck-lists-fragment" data-has-content="<?php echo $hasDeckLists ? '1' : '0'; ?>">
    <?php if ($hasDeckLists) : ?>
        <h4>Deck lists</h4>
        <table id="decklists-table" style="width:100%;">
            <?php include APP_ROOT . '/includes/fragments/deckdetail_export_list.php'; ?>
            <?php include APP_ROOT . '/includes/fragments/deckdetail_missing.php'; ?>
            <?php include APP_ROOT . '/includes/fragments/deckdetail_buy_missing.php'; ?>
        </table> <?php
    endif; ?>
</div>
