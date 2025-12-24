<?php

/*
Version:     1.1
Date:        24/12/25
Name:        deckdetail_deck_lists.php
Purpose:     Deck detail deck lists fragment wrapper.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
?>
<div id="deck-lists-fragment">
    <?php if ($total + $sidetotal > 0) : ?>
        <h4>Deck lists</h4>
        <table id="decklists-table" style="width:100%;">
            <?php include __DIR__ . '/deckdetail_export_list.php'; ?>
            <?php include __DIR__ . '/deckdetail_missing.php'; ?>
            <?php include __DIR__ . '/deckdetail_buy_missing.php'; ?>
        </table> <?php
    endif; ?>
</div>
