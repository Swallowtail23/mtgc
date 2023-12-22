<?php 
/* Version:     6.0
    Date:       09/12/23
    Name:       login.php
    Purpose:    Check for existing session, process login.
    Notes:      {none}
    To do:      
    
    1.0
                Initial version
 *  2.0 
 *              Moved from writelog to Message class
 *              Reset bad login count to zero after a good login
 *  3.0
 *              Moved to password-verify
 *  4.0
 *              Corrected logic around invalid user emails
 * 
 *  5.0
 *              Added Cloudflare Turnstile protection
 * 
 *  6.0         09/12/23
 *              Add redirect capture
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();

// Temporary variable to store a redirection URL
$redirectUrl = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : null;
/*
 *  check if user is already logged in. If yes, display error and redirect to
 *  index.php. If no - session destroy and display login page.
 */
if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) :   
    echo "<meta http-equiv='refresh' content='2;url=index.php'>";
    /*
     *  Stub HTML for error display.
     */
    ?>
    <!DOCTYPE html>
        <head>
        <title> MtG collection - login</title>
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
        <?php include('includes/googlefonts.php'); ?>
        <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
        </head>
        <body id="loginbody" class="body">
        <div id="loginheader">    
            <h2 id='h2'> MtG collection</h2>
            You are already logged in!
        </div>
        </body>
    </html>
    <?php
    exit();
else:
    session_destroy();
    ini_set('session.name', '5VDSjp7k-n-_yS-_');
    session_start(); // Start a new session after destroying the previous one

    // Reassign the redirect URL to the new session
    if ($redirectUrl) :
        $_SESSION['redirect_url'] = $redirectUrl;
    endif;
endif;
/*
 *  Continuing to load login page.
 */
header ("Cache-Control: max-age=0");
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
// Find CSS Version
$cssver = cssver();
$msg = new Message;

?>
<!DOCTYPE html>
<head>
    <title> MTG collection </title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
    <?php include('includes/googlefonts.php'); ?>
    <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body id="loginbody" class="body">
    <?php include_once("includes/analyticstracking.php") ?>
    <div id="loginheader">    
        <h2 id='h2'> MtG collection</h2>
        <?php
        // Cloudflare Turnstile
        use andkab\Turnstile\Turnstile;
        if ($turnstile === 1 AND isset($_POST['cf-turnstile-response'])):
            $turnstile = new Turnstile("$turnstile_secret_key");
            $verifyResponse = $turnstile->verify($_POST['cf-turnstile-response'], $_SERVER['REMOTE_ADDR']);
            if ($verifyResponse->isSuccess()):
                // successfully verified captcha resolving
                $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Cloudflare Turnstile success from {$_SERVER['REMOTE_ADDR']}",$logfile);
            elseif ($verifyResponse->hasErrors()):
                foreach ($verifyResponse->errorCodes as $errorCode):
                    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Cloudflare Turnstile failure $errorCode from {$_SERVER['REMOTE_ADDR']}",$logfile);
                endforeach;
                session_destroy();
                echo "<meta http-equiv='refresh' content='0;url=login.php?turnstilefail=yes'>";
                exit();
            else:
                $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Cloudflare Turnstile failure (unknown) from {$_SERVER['REMOTE_ADDR']}",$logfile);
                session_destroy();
                echo "<meta http-equiv='refresh' content='0;url=login.php?turnstilefail=yes'>";
                exit();
            endif;
        endif;
        if ((isset($_GET['turnstilefail'])) AND ($_GET['turnstilefail']=="yes")): //Turnstile fail
            echo '"Captcha" fail... Returning to login...';
            session_destroy();
            echo "<meta http-equiv='refresh' content='5;url=login.php'>";
            exit();
        endif;
        if ((isset($_POST['ac'])) AND ($_POST['ac']=="log")):           //Login form has been submitted  
            if (isset($_POST['password'], $_POST['email'])):            //Login form contains data
                
                $rawemail = $_POST['email'];
                $password = $_POST['password'];
                
                $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon called for '$rawemail' from {$_SERVER['REMOTE_ADDR']}",$logfile);
                
                $email = trim($rawemail);
                $email = filter_var($email, FILTER_SANITIZE_EMAIL);
                if (!empty($email) && !empty($password)):
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)):
                        $badlog = new UserStatus($db,$logfile,$email);
                        $badlog_result = $badlog->GetBadLogin();
                        if ($badlog_result['count'] !== null AND $badlog_result['count'] < ($Badloglimit)):
                            $pwval = new PasswordCheck($db, $logfile);
                            $pwval_result = $pwval->PWValidate($email,$password);
                            if ($pwval_result === 10):
                                // username and password checks out OK - carry on!
                                $userstat = new UserStatus($db,$logfile,$email);
                                $userstat_result = $userstat->GetUserStatus();
                                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"UserStatus for $email is {$userstat_result['code']}",$logfile);
                                if ($userstat_result['code'] === 0):
                                    trigger_error("[ERROR] Login.php: user status check failure", E_USER_ERROR);
                                elseif ($userstat_result['code'] === 1): //pwdchg required
                                    $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"UserStatus for $email is ok, but password change required",$logfile);
                                    $_SESSION["logged"] = TRUE;
                                    $user = $_SESSION["user"] = $userstat_result['number'];
                                    $_SESSION["useremail"] = $email;
                                    $_SESSION["chgpwd"] = TRUE;
                                    $_SESSION['just_logged_in'] = TRUE; // Set a flag indicating a fresh login to prevent redirect loops on chgpwd
                                    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon validated for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                elseif ($userstat_result['code'] === 2): //locked
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Logon attempt for locked account $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                elseif ($userstat_result === 3): //disabled
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Logon attempt for disabled account $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                elseif ($userstat_result['code'] === 10): //active
                                    session_regenerate_id();
                                    $_SESSION["logged"] = TRUE;
                                    $user = $_SESSION["user"] = $userstat_result['number'];
                                    $_SESSION["useremail"] = $email;
                                    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon validated for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                    if($badlog_result['count'] != 0):
                                        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon ok for $email, clearing non-zero bad login count ({$badlog_result['count']})",$logfile);
                                        $zerobadlog = new UserStatus($db,$logfile,$email);
                                        $zerobadlog->ZeroBadLogin();
                                    endif;
                                else:
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect status for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                endif;            
                            elseif ($pwval_result === 2):
                                echo 'Incorrect username/password. Please try again.';
                                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt by valid user $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                                $obj = new UserStatus($db,$logfile,$email);
                                $obj->IncrementBadLogin();
                                session_destroy();
                                echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                                exit();
                            endif;
                        elseif($badlog_result['count'] === null):
                            echo 'Incorrect username/password. Please try again.';
                            $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt by invalid user $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                            session_destroy();
                            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                            exit();
                        else:
                            echo 'Too many incorrect logins. Use the reset button to contact admin. Returning to login...';
                            $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Too many incorrect logins from $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                            $obj = new UserStatus($db,$logfile,$email);
                            $obj->TriggerLocked();
                            session_destroy();
                            echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                            exit();
                        endif;
                    else:
                        echo 'Incorrect data submitted. Returning to login...';
                        $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect data sent from '$email' from {$_SERVER['REMOTE_ADDR']} (FILTER_VALIDATE_EMAIL failed)",$logfile);
                        session_destroy();
                        echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                        exit();
                    endif;
                else:
                    echo 'Incorrect data submitted. Returning to login...';
                    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect data sent from '$email' from {$_SERVER['REMOTE_ADDR']} (email or password is empty)",$logfile);
                    session_destroy();
                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                    exit();
                endif;
            else:
                echo 'Incorrect data submitted. Returning to login...';
                $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect data sent from $email from {$_SERVER['REMOTE_ADDR']} (email or password variables not set)",$logfile);
                session_destroy();
                echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                exit();
            endif;
        endif;
        //Check if login has been successful
        if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) : 
            $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"User $email logged in from {$_SERVER['REMOTE_ADDR']}",$logfile);
            //Write user's login date to the user table in the database (help track inactive users)
            loginstamp($email);
            //Is maintenance mode enabled?
            $mtcestatus = mtcemode($user);
            if ($mtcestatus == 1):
                echo "<br>Site is undergoing maintenance, please try again later...";
                session_destroy();
                echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                exit();
            elseif ($userstat_result['admin'] == 1):
                $_SESSION['admin'] = TRUE;
                echo "You are logged in!";  //admin login notice
            else:
                echo "You are logged in";   //normal user login notice
                $_SESSION['admin'] = FALSE;
            endif;
            // Check for chgpwd, or if there is a redirect URL set in the session
            if (isset($_SESSION["chgpwd"]) && $_SESSION["chgpwd"] === TRUE):
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User $email being redirected to profile.php for password change",$logfile);
                echo "<meta http-equiv='refresh' content='2;url=profile.php'>";
                exit();
            elseif (isset($_SESSION['redirect_url'])) :
                $redirectUrl = $_SESSION['redirect_url'];
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User $email being redirected to requested URL: '$redirectUrl'",$logfile);
                unset($_SESSION['redirect_url']); // Clear the redirect URL from the session
                echo "<meta http-equiv='refresh' content='0;url=$redirectUrl'>";
                exit();
            else: // go to index.php
                $msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"User $email being redirected to index.php",$logfile);
                echo "<meta http-equiv='refresh' content='2;url=index.php'>";
            endif;
        else:
            echo '<br><form action="login.php" method="post"><input type="hidden" name="ac" value="log"> '; 
            echo "<input class='textinput loginfield' type='email' name='email' autofocus placeholder='EMAIL'/>"; 
            echo "<br><br>";
            echo "<input class='textinput loginfield' type='password' name='password' placeholder='PASSWORD'/><br>";
            if ($turnstile === 1):
                echo "<br>";
                echo "<div class='cf-turnstile' data-sitekey='$turnstile_site_key' data-theme='light'></div>";
            endif;
            echo '<input type="submit" id="loginsubmit" value="LOGIN" />'; 
            echo '</form><br>';?>
            <div class='loginpagebutton'>
                <a href='reset.php'>RESET</a>
            </div> <?php 
        endif; ?>
    </div>
</body>
</html>