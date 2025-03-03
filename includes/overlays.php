<?php 
/* Version:     2.0
    Date:       20/11/23
    Name:       overlays.php
    Purpose:    buttons overlay
    Notes:      {none}
 * 
    1.0         17/10/16
                Initial version
 * 
 *  2.0         20/11/23
 *              Added floating button to enable/disable Collection View to grid view
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
    if(isset($floating_button) AND $floating_button === true AND $collection_view === 1 AND isset($scope) AND ($scope !== 'notcollection')): ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view off"> 
            <input type="checkbox" id="float_cview" class="option_toggle" checked="true" value="on" />
            <div id="slider_cview" class="slider round material-symbols-outlined"></div>
        </label>  <?php
    elseif(isset($floating_button) AND $floating_button === true AND $collection_view === 0 AND isset($scope) AND ($scope !== 'notcollection')): ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view on"> 
            <input type="checkbox" id="float_cview" class="option_toggle" value="on" />
            <div id="slider_cview" class="slider round material-symbols-outlined book_2"></div>
        </label>  <?php
    endif; 
?>
</div>