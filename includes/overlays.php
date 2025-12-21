<?php

/*
Version:     2.1
Date:        26/11/25
Name:        overlays.php
Purpose:     Buttons overlay
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;
?>

<div id="logout">
    <a href="/logout.php"><span class="material-symbols-outlined logouta">logout</span></a>
</div>

<div id="float_cview_div">
<?php
if (
    isset($floating_button)
    and $floating_button === true
    and $collection_view === 1
    and isset($scope)
    and ($scope !== 'notcollection' && $scope !== 'mycollection')
) : ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view off">
            <input type="checkbox" id="float_cview" class="option_toggle" checked="true" value="on" />
            <div id="slider_cview" class="slider round material-symbols-outlined"></div>
        </label>  <?php
elseif (
    isset($floating_button)
    and $floating_button === true
    and $collection_view === 0
    and isset($scope)
    and ($scope !== 'notcollection' && $scope !== 'mycollection')
) : ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view on">
            <input type="checkbox" id="float_cview" class="option_toggle" value="on" />
            <div id="slider_cview" class="slider round material-symbols-outlined book_2"></div>
        </label>  <?php
endif;
?>
</div>
