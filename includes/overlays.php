<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       overlays.php
    Purpose:    logout button overlay
    Notes:      {none}
 * 
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id="logout">
    <a href="/logout.php"></a>
</div>

<div id="float_cview_div">
<?php
    if(isset($floating_button) AND $floating_button === true AND $collection_view === 1 AND isset($scope) AND $scope !== 'mycollection'): ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view off"> 
            <input type="checkbox" id="float_cview" class="option_toggle" checked="true" value="on" />
            <div class="slider round"></div>
        </label>  <?php
    elseif(isset($floating_button) AND $floating_button === true AND $collection_view === 0 AND isset($scope) AND $scope !== 'mycollection'): ?>
        <label id="floating_button_label" class="floating-button" title="Toggle collection view on"> 
            <input type="checkbox" id="float_cview" class="option_toggle" value="on" />
            <div class="slider round"></div>
        </label>  <?php
    else:
        
    endif; 
?>
</div>