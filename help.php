<?php

/*
Version:     2.24
Date:        12/01/26
Name:        help.php
Purpose:     Provides a help submission form and place for help notes.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\MyPHPMailer;

// Bootstrap
$appContext = require __DIR__ . '/bootstrap_secure.php';

$siteTitle = (string) $appConfig->general('title', '');
$adminEmail = (string) $appConfig->email('adminEmail', '');
$emailEnabled = (bool) $appConfig->email('enabled', false);

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - help</title>
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body id="body" class="body">
<?php
include_once APP_ROOT . '/includes/analyticstracking.php';
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php';
$name = ucfirst($userName);
?>
<div id='page'>
    <div class='staticpagecontent'>
            <div id="printtitle" class="headername">
                <img src="images/white_m.png"><?php echo $siteTitleEsc;?>
            </div>
            <h2 class='h2pad'>Contact or report an issue</h2>
            <?php
            if (isset($_REQUEST['action'])) :
                $action = $_REQUEST['action'];
            endif;
            if ((!isset($action)) or ($action == "")) :
                if (!$emailEnabled) :
                    echo "This system has global email functionality disabled, "
                         . "reporting is not available<br>";
                else :
                    if (isset($_SERVER['HTTP_REFERER'])) :
                        $referpage = $_SESSION["referpage"] = $_SERVER['HTTP_REFERER'];
                    else :
                        $host = $_SERVER['HTTP_HOST'];
                        $uri = $_SERVER['REQUEST_URI'];
                        $referpage = "https://" . $host . $uri;
                    endif;
                    ?>
                    <form  action="#" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit">
                    Your name:<br>
                    <?php echo "<input class='disabledtext' "
                            . "name='name' type='text' placeholder='$name' "
                            . "value='$name' disabled size='30'/><br>"; ?>
                    Your email:<br>
                    <?php echo "<input class='disabledtext' "
                            . "name='email' type='email' placeholder='$userEmail' "
                            . "value='$userEmail' disabled size='30'/><br>"; ?>
                    Referring page:<br>
                    <?php echo "<input class='disabledtext "
                            . "disabledtextwide' name='page' type='text' "
                            . "placeholder='$referpage' value='$referpage' "
                            . "disabled size='60'/><br>"; ?>
                    Your message:<br>
                    <textarea class='messagetext textinput' name="message" rows="7" cols="30"></textarea><br>
                    <input class='inline_button stdwidthbutton' type="submit" value="SEND MESSAGE"/>
                    </form>
                    <?php
                endif;
            else :
                $referpage = $_SESSION["referpage"];
                $message = wordwrap($_REQUEST['message'], 70);
                if (($name == "") || ($userEmail == "") || ($message == "")) :
                    echo "All fields are required, please fill <a href=\"\">the form</a> again.";
                else :
                    $from = "From: $name<$userEmail>\r\nReturn-path: $userEmail";
                    if ($referpage != '') :
                        $subject = "Message sent using your contact form from $referpage";
                    else :
                        $subject = "Message sent using your contact form";
                    endif;
                    if (isset($emailEnabled) && $emailEnabled === true) :
                        $mailer = new MyPHPMailer(true, $appConfig);
                        $mailResult = $mailer->sendEmail($adminEmail, false, $subject, $message, '', '', '');
                        if ($mailResult === true) :
                            echo "Email sent!";
                        else :
                            $msg->logMessage(
                                '[ERROR]',
                                "Help form email failed for $userEmail (subject: $subject)"
                            );
                            echo "Unable to send email; please try again later.";
                        endif;
                    else :
                        $msg->logMessage(
                            '[NOTICE]',
                            "Email disabled; contact form not emailed (from $userEmail, subject: $subject)"
                        );
                        echo "Email is disabled; your message was not sent.";
                    endif;
                    echo "<meta http-equiv='refresh' content='2;url=help.php'>";
                endif;
                $_SESSION["referpage"] = '';
            endif;
            ?>
    <hr class="styled">
    <h3 class="shallowh3">Help</h3>
    <b>Known issues, bugs and other crawlies</b><br>
    For known problems, see this page: <a href="issues.php">Issues</a><br><br>
    <b>Card data</b><br>
    Card data is refreshed each night at midnight, with a full synchronisation
    to Scryfall's card database. Errors, omissions, etc. will remain until resolved
    at <a href='https://www.scryfall.com'>Scryfall</a>
    <br><br><b>Prices</b><br>
    Prices are updated each night from Scryfall for every card in the database.
    When going to a card detail page, if the price was fetched more than
    <?php echo $gameRules->getFloat('max_data_age_in_hours', 0.0); ?> hours, it will automatically update.
    If no price is available for the card, this will be because Scryfall do not currently have a price.
    <br><br><b>Card updates</b><br>
    Please use the form above if you find an issue with a card, or missing cards.
    Clicking on Help from a page that has a problem will automatically include that
    page's address in the contact form that gets sent.
    <br>
&nbsp;
</div>
</div>
<?php
require APP_ROOT . '/includes/footer.php';
?>
</body>
</html>
