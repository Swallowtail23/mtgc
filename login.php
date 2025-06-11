<?php 
/* Version:     7.0
    Date:       28/02/25
    Name:       login.php
    Purpose:    Check for existing session, process login.
    Notes:      {none}
    To do:      
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson

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
 * 
 *  6.1         20/01/24
 *              Move to logMessage
 * 
 *  7.0         28/02/25
 *              Trusted devices capability
*/
if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();

// Load required files for database access
require_once('includes/ini.php');               //Initialise and load ini file
require_once('includes/error_handling.php');
require_once('includes/functions.php');         //Includes basic functions for non-secure pages

// Initialize message object for logging
$msg = new Message($logfile);

if (!isset($db) || !$db instanceof mysqli) {
    $msg->logMessage('[ERROR]', "Database connection is null or invalid in login.php");
    die("A database error occurred. Please try again later.");
}

// Find CSS Version
$cssver = cssver();

// Temporary variable to store a redirection URL
$redirectUrl = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : null;
/*
 *  Check for trusted device token before checking regular login
 */
$trusted_login = false;
$trusted_device_user = false;

// Debug log beginning of execution
$msg->logMessage('[DEBUG]', "Starting login.php execution. Checking for trusted device.");

if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== TRUE):
    // Not already logged in, check for trusted device token
    require_once('classes/trusteddevicemanager.class.php');
    
    // Database connection should be available from ini.php
    $msg->logMessage('[DEBUG]', "Checking for trusted device cookie with db connection: " . (isset($db) ? "valid" : "missing"));
    $deviceManager = new TrustedDeviceManager($db, $logfile);
    // Try to validate trusted device token
    $trusted_device_user = $deviceManager->validateTrustedDevice();
    $trusted_device_user = (int) $trusted_device_user;
    $msg->logMessage('[DEBUG]', "Output from Trusted device user check: $trusted_device_user");
    
    if ($trusted_device_user !== false):
        // Token is valid, auto-login the user
        $user_query = "SELECT usernumber, username, email, admin FROM users WHERE usernumber = ? AND status = 'active'";
        $stmt = $db->prepare($user_query);
        
        if ($stmt):
            $stmt->bind_param("i", $trusted_device_user);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1):
                $stmt->bind_result($usernumber, $username, $useremail, $admin);
                $stmt->fetch();
                
                // Set up session for auto-login
                $_SESSION["logged"] = TRUE;
                $_SESSION["user"] = $usernumber;
                $_SESSION["useremail"] = $useremail;
                $_SESSION['admin'] = (bool) $admin;
                
                $msg->logMessage('[NOTICE]', "Auto-login via trusted device for user $useremail");
                
                // Update last login timestamp
                if (!loginstamp($useremail)) {
                    $msg->logMessage('[ERROR]', "Failed to update last login timestamp for $useremail");
                }
                
                $trusted_login = true;
                // Immediately redirect, skipping the login form
                $redirectTarget = $_SESSION['redirect_url'] ?? 'index.php';
                header("Location: $redirectTarget");
                exit();                
            endif;
            
            $stmt->close();
        endif;
    endif; // End of if trusted_device_user check
endif;

/*
 *  check if user is already logged in. If yes, display error and redirect to
 *  index.php. If no - session destroy and display login page.
 */

// Normal login flow check
if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) :
    // After initialize message object and functions
    if (isset($msg)) {
        $msg->logMessage('[DEBUG]', "User already logged in, showing already logged in page");
    }
    echo "<meta http-equiv='refresh' content='2;url=index.php'>";
    /*
     *  Stub HTML for error display.
     */
    ?>
    <!DOCTYPE html>
        <head>
        <title> <?php echo $siteTitle;?> - login</title>
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
        <?php include('includes/googlefonts.php'); ?>
        <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
        </head>
        <body id="loginbody" class="body">
        <div id="loginheader">    
            <h2 id='h2'><?php echo $siteTitle;?></h2>
            <?php if ($trusted_login): ?>
                Welcome back! You've been automatically signed in using a trusted device.
            <?php else: ?>
                You are already logged in!
            <?php endif; ?>
        </div>
        </body>
    </html>
    <?php
    exit();
else:
    session_destroy();
    startCustomSession(); // Start a new session after destroying the previous one

    // Reassign the redirect URL to the new session
    if ($redirectUrl) :
        $_SESSION['redirect_url'] = $redirectUrl;
    endif;
endif;
/*
 *  Continuing to load login page.
 */
header ("Cache-Control: max-age=0");
// Files already loaded above

// Message object already initialized above
$msg->logMessage('[DEBUG]', "Mid-load check: db=" . (isset($db) ? "valid" : "null"));

// Log key variables for debugging
$msg->logMessage('[DEBUG]', "Login.php loaded. POST vars: " . 
    "trust_device=" . (isset($_POST['trust_device']) ? $_POST['trust_device'] : 'not set') . ", " .
    "redirect_to=" . (isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'not set'));
$msg->logMessage('[DEBUG]', "Session vars: " . 
    "logged=" . (isset($_SESSION["logged"]) ? $_SESSION["logged"] : 'not set') . ", " .
    "user=" . (isset($_SESSION["user"]) ? $_SESSION["user"] : 'not set') . ", " .
    "useremail=" . (isset($_SESSION["useremail"]) ? $_SESSION["useremail"] : 'not set'));

?>
<!DOCTYPE html>
<head>
    <title><?php echo $siteTitle;?></title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
    <?php include('includes/googlefonts.php'); ?>
    <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body id="loginbody" class="body">
    <?php include_once("includes/analyticstracking.php") ?>
    <div id="loginheader">    
        <h2 id='h2'><?php echo $siteTitle;?></h2>
        <?php
        // Cloudflare Turnstile
        use andkab\Turnstile\Turnstile;
        if ($turnstile === 1 AND isset($_POST['cf-turnstile-response'])):
            $turnstile = new Turnstile("$turnstile_secret_key");
            $verifyResponse = $turnstile->verify($_POST['cf-turnstile-response'], $_SERVER['REMOTE_ADDR']);
            if ($verifyResponse->isSuccess()):
                // successfully verified captcha resolving
                $msg->logMessage('[NOTICE]',"Cloudflare Turnstile success from {$_SERVER['REMOTE_ADDR']}");
            elseif ($verifyResponse->hasErrors()):
                foreach ($verifyResponse->errorCodes as $errorCode):
                    $msg->logMessage('[NOTICE]',"Cloudflare Turnstile failure $errorCode from {$_SERVER['REMOTE_ADDR']}");
                endforeach;
                session_destroy();
                echo "<meta http-equiv='refresh' content='0;url=login.php?turnstilefail=yes'>";
                exit();
            else:
                $msg->logMessage('[NOTICE]',"Cloudflare Turnstile failure (unknown) from {$_SERVER['REMOTE_ADDR']}");
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

                $email = filter_var((trim($rawemail)), FILTER_SANITIZE_EMAIL);
                $msg->logMessage('[NOTICE]',"Logon called for '$email' from {$_SERVER['REMOTE_ADDR']}");
                if (!empty($email) && !empty($password)):
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)):
                        $badlog = new UserStatus($db,$logfile,$email);
                        $badlog_result = $badlog->GetBadLogin();
                        if ($badlog_result['count'] !== null AND $badlog_result['count'] < ($Badloglimit)):
                            $pwval = new PasswordCheck($db, $logfile, $siteTitle);
                            $pwval_result = $pwval->PWValidate($email,$password);
                            if ($pwval_result === 10):
                                // username and password checks out OK - carry on!
                                $userstat = new UserStatus($db,$logfile,$email);
                                $userstat_result = $userstat->GetUserStatus();
                                $msg->logMessage('[DEBUG]',"UserStatus for $email is {$userstat_result['code']}");
                                if ($userstat_result['code'] === 0):
                                    trigger_error("[ERROR] Login.php: user status check failure", E_USER_ERROR);
                                elseif ($userstat_result['code'] === 2): //locked
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->logMessage('[ERROR]',"Logon attempt for locked account $email from {$_SERVER['REMOTE_ADDR']}");
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                elseif ($userstat_result === 3): //disabled
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->logMessage('[ERROR]',"Logon attempt for disabled account $email from {$_SERVER['REMOTE_ADDR']}");
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                elseif ($userstat_result['code'] === 1 || $userstat_result['code'] === 10): //active or pwdchg required
                                    // Check for 2FA requirement
                                    $tfaManager = new TwoFactorManager($db, $smtpParameters, $serveremail, $logfile);
                                    
                                    if ($tfaManager->isEnabled($userstat_result['number'])):
                                        // 2FA is enabled, store credentials for verification page
                                        session_regenerate_id(true);
                                        $_SESSION["user_pending_2fa"] = $userstat_result['number'];
                                        $_SESSION["useremail_pending_2fa"] = $email;
                                        $_SESSION["admin_pending_2fa"] = $userstat_result['admin'] == 1;
                                        $_SESSION["chgpwd_pending_2fa"] = $userstat_result['code'] === 1;
                                        
                                        // Clear bad login count if user entered correct password
                                        if($badlog_result['count'] != 0):
                                            $msg->logMessage('[NOTICE]',"Logon (first factor) ok for $email, clearing non-zero bad login count ({$badlog_result['count']})");
                                            $zerobadlog = new UserStatus($db,$logfile,$email);
                                            $zerobadlog->ZeroBadLogin();
                                        endif;
                                        
                                        // If there was a redirect URL, preserve it
                                        if (isset($_SESSION['redirect_url'])):
                                            $_SESSION['redirect_url_after_2fa'] = $_SESSION['redirect_url'];
                                        endif;
                                        
                                        // Start 2FA verification process and redirect
                                        $tfaManager->startVerification($userstat_result['number'], $email);
                                        $msg->logMessage('[NOTICE]',"Password validated for $email, redirecting to 2FA verification");
                                        header("Location: verify_2fa.php");
                                        exit();
                                    else:
                                        // No 2FA required, proceed with normal login
                                        $msg->logMessage('[NOTICE]',"Regenerating session ID after successful login");
                                        session_regenerate_id(true);
                                        $_SESSION["logged"] = TRUE;
                                        $_SESSION["user"] = $usernumber = $userstat_result['number'];
                                        $_SESSION["useremail"] = $email;

                                        // If password change required, set flag
                                        if ($userstat_result['code'] === 1):
                                            $_SESSION["chgpwd"] = TRUE;
                                            $_SESSION['just_logged_in'] = TRUE; // Set flag to prevent redirect loops
                                        endif;
                                        
                                        $msg->logMessage('[NOTICE]',"Logon validated for $email from {$_SERVER['REMOTE_ADDR']}");
                                        if($badlog_result['count'] != 0):
                                            $msg->logMessage('[NOTICE]',"Logon ok for $email, clearing non-zero bad login count ({$badlog_result['count']})");
                                            $zerobadlog = new UserStatus($db,$logfile,$email);
                                            $zerobadlog->ZeroBadLogin();
                                        endif;
                                    endif;
                                else:
                                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                                    $msg->logMessage('[ERROR]',"Failed logon attempt: Incorrect status for $email from {$_SERVER['REMOTE_ADDR']}");
                                    session_destroy();
                                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                                    exit();
                                endif;            
                            elseif ($pwval_result === 2):
                                echo 'Incorrect username/password. Please try again.';
                                $msg->logMessage('[ERROR]',"Failed logon attempt by valid user $email from {$_SERVER['REMOTE_ADDR']}");
                                $obj = new UserStatus($db,$logfile,$email);
                                $obj->IncrementBadLogin();
                                session_destroy();
                                echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                                exit();
                            endif;
                        elseif($badlog_result['count'] === null):
                            echo 'Incorrect username/password. Please try again.';
                            $msg->logMessage('[ERROR]',"Failed logon attempt by invalid user $email from {$_SERVER['REMOTE_ADDR']}");
                            session_destroy();
                            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                            exit();
                        else:
                            echo 'Too many incorrect logins. Use the reset button to contact admin. Returning to login...';
                            $msg->logMessage('[NOTICE]',"Too many incorrect logins from $email from {$_SERVER['REMOTE_ADDR']}");
                            $obj = new UserStatus($db,$logfile,$email);
                            $obj->TriggerLocked();
                            session_destroy();
                            echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                            exit();
                        endif;
                    else:
                        echo 'Incorrect data submitted. Returning to login...';
                        $msg->logMessage('[NOTICE]',"Failed logon attempt: Incorrect data sent from '$email' from {$_SERVER['REMOTE_ADDR']} (FILTER_VALIDATE_EMAIL failed)");
                        session_destroy();
                        echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                        exit();
                    endif;
                else:
                    echo 'Incorrect data submitted. Returning to login...';
                    $msg->logMessage('[NOTICE]',"Failed logon attempt: Incorrect data sent from '$email' from {$_SERVER['REMOTE_ADDR']} (email or password is empty)");
                    session_destroy();
                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                    exit();
                endif;
            else:
                echo 'Incorrect data submitted. Returning to login...';
                $msg->logMessage('[NOTICE]',"Failed logon attempt: Incorrect data sent from $email from {$_SERVER['REMOTE_ADDR']} (email or password variables not set)");
                session_destroy();
                echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                exit();
            endif;
        endif;
        //Check if login has been successful
        if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) :
            $msg->logMessage('[NOTICE]',"User $email logged in from {$_SERVER['REMOTE_ADDR']}");
            //Write user's login date to the user table in the database (help track inactive users)
            if (!loginstamp($email)) {
                $msg->logMessage('[ERROR]', "Failed to update last login timestamp for $email");
            }
            //Is maintenance mode enabled?
            $mtcestatus = mtcemode($usernumber);
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
                $msg->logMessage('[DEBUG]',"User $email being redirected to profile.php for password change");
                echo "<meta http-equiv='refresh' content='2;url=profile.php'>";
                exit();
            else:
                // Show the trust device prompt
                ?>
                <?php $msg->logMessage('[DEBUG]', "Showing trust device prompt");
                header("Location: trust_device.php?redirect_to=" . urlencode($_SESSION['redirect_url'] ?? 'index.php'));
                exit();
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
