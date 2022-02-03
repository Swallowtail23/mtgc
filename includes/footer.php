<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       footer.php
    Purpose:    PHP script to display footer
    Notes:      {none}
        
    1.0
                Initial version
 */

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<div id="footer">
    <br>
    &copy; <?php echo $copyright;?>
    <br><br>
    <a href="https://jigsaw.w3.org/css-validator/validator?uri=https://www.mtgcollection.info/css/style.css<?php echo $cssver?>.css">
                <img
                src="/images/valid_css3.png"
                alt="Valid CSS!" />
    </a><br><br>
    <a href="https://validator.w3.org/check?uri=https://www.mtgcollection.info">
                <img
                src="/images/valid_html5.png"
                alt="Valid HTML5!" />
            </a>
    
</div>
