<?php
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

echo <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-TLT1JVWWQR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-TLT1JVWWQR');
</script>

HTML;
