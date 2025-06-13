<?php
/* Version:     2.0
    Date:       13/06/25
    Name:       error.php
    Purpose:    Very basic page with no database connectivity
    Notes:      Ini file is parsed with parse_ini_file, not INI class, as classes
                not loaded in this page
    To do:      -
    
    1.0
                Initial version

    2.0         13/06/25
                Use configdefaults file        
*/

require __DIR__ . '/config_defaults.php';

// 1) parse the ini with sections
$ini_array = parse_ini_file('/opt/mtg/mtg_new.ini', true);

// 2) merge defaults
foreach ($defaults as $section => $kv) {
    if (! isset($ini_array[$section])) {
        $ini_array[$section] = $kv;
        continue;
    };
    foreach ($kv as $key => $val) {
        if (! isset($ini_array[$section][$key]) 
            || $ini_array[$section][$key] === ''
        ) {
            $ini_array[$section][$key] = $val;
        };
    };
};

//Copyright string, tier, title and css
$copyright = $ini_array['general']['Copyright'];
$tier      = $ini_array['general']['tier'] === 'dev' ? 'dev' : 'prod';
$siteTitle = $ini_array['general']['title'];
$cssver = "";
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title> <?php echo $siteTitle;?> error page</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
// Includes first - menu and header            
if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) :
    require('includes/overlays.php');
endif;
require('includes/header.php'); ?>
<div id='menubuttondiv' class="togglemenu">    
    <a href="#" id='toggle-menu'><span class="material-symbols-outlined menu">menu</span></a>
</div>
<div id="menu">
    <div class='nav_nodivider'><a title="Home" href="/">Home</a></div>
</div>
<div id="page">
    <div class="staticpagecontent">
        <h3>Error</h3>
        We've encountered a problem!<br><br>
        That page returned an error, and details have been emailed to site admins. <br>
        Please try again later.
        <br>
        &nbsp;
        <br>
    </div>
</div>

<?php 
require('includes/footer.php'); ?>        
</body>
</html>
