<?php

/*
Version:     1.11
Date:        12/01/26
Name:        footer.php
Purpose:     PHP script to display footer
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$copyright = (string) $appConfig->general('copyright', '');

if (__FILE__ === $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$cssValidatorUrl = 'https://jigsaw.w3.org/css-validator/validator?uri='
    . 'https://www.mtgcollection.info/css/style' . $cssver . '.css';
?>

<div id="footer">
    <br>
    &copy; <?php echo $copyright;?>
    <br><br>
    <a href="<?php echo $cssValidatorUrl;?>">
        <img src="/images/valid_css3.png" alt="Valid CSS!">
    </a><br><br>
    <a href="https://validator.w3.org/check?uri=https://www.mtgcollection.info">
        <img src="/images/valid_html5.png" alt="Valid HTML5!">
    </a>
</div>
