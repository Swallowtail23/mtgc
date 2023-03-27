<?php 
/* Version:     4.0
    Date:       18/03/23
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
*/

session_start();
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
        <title> MTG collection </title>
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
endif;
/*
 *  Continuing to load login page.
 */
header ("Cache-Control: max-age=0");
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
// Find CSS Version
$cssver = cssver();

?>
<!DOCTYPE html>
<head>
<title> MTG collection </title>
<link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
<?php include('includes/googlefonts.php'); ?>
<meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
</head>
<body id="loginbody" class="body">
    <?php include_once("includes/analyticstracking.php") ?>
<div id="loginheader">    
    <h2 id='h2'> MtG collection</h2>
<?php
if ((isset($_POST['ac'])) AND ($_POST['ac']=="log")):           //Login form has been submitted  
    if (isset($_POST['password']) AND isset($_POST['email'])):  //Login form contains data
        $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon called for {$_POST['email']} from {$_SERVER['REMOTE_ADDR']}",$logfile);
        $email = $_POST['email'];
        $password = $_POST['password'];
        $badlog = new UserStatus;
        $badlog_result = $badlog->GetBadLogin($email);
        if ($badlog_result['count'] !== null AND $badlog_result['count'] < ($Badloglimit)):
            $pwval = new PasswordCheck;
            $pwval_result = $pwval->PWValidate($email,$password);
            if ($pwval_result === 10):
                // username and password checks out OK - carry on!
                $userstat = new UserStatus;
                $userstat_result = $userstat->GetUserStatus($email);
                $msg = new Message;$msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"UserStatus for $email is {$userstat_result['code']}",$logfile);
                if ($userstat_result['code'] === 0):
                    trigger_error("[ERROR] Login.php: user status check failure", E_USER_ERROR);
                elseif ($userstat_result['code'] === 1): //pwdchg required
                    $msg = new Message;$msg->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"UserStatus for $email is ok, but password change required",$logfile);
                    $_SESSION["logged"]=TRUE;
                    $user = $_SESSION["user"] = $userstat_result['number'];
                    $_SESSION["useremail"] = $email;
                    $_SESSION["chgpwd"]=TRUE;
                    $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon validated for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                elseif ($userstat_result['code'] === 2): //locked
                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                    $msg = new Message;$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Logon attempt for locked account $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    session_destroy();
                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                    exit();
                elseif ($userstat_result === 3): //disabled
                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                    $msg = new Message;$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Logon attempt for disabled account $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    session_destroy();
                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                    exit();
                elseif ($userstat_result['code'] === 10): //active
                    $_SESSION["logged"]=TRUE;
                    $user = $_SESSION["user"] = $userstat_result['number'];
                    $_SESSION["useremail"]=$email;
                    $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon validated for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    if($badlog_result['count'] != 0):
                        $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Logon ok for $email, clearing non-zero bad login count ({$badlog_result['count']})",$logfile);
                        $zerobadlog = new UserStatus;
                        $zerobadlog->ZeroBadLogin($email);
                    endif;
                else:
                    echo 'There is a problem with your account. Contact the administrator. Returning to login...';
                    $msg = new Message;$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect status for $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                    session_destroy();
                    echo "<meta http-equiv='refresh' content='5;url=login.php'>";
                    exit();
                endif;            
            elseif ($pwval_result === 2):
                echo 'Incorrect username/password. Please try again.';
                $msg = new Message;$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt by valid user $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
                $obj = new UserStatus;$obj->IncrementBadLogin($email);
                session_destroy();
                echo "<meta http-equiv='refresh' content='3;url=login.php'>";
                exit();
            endif;
        elseif($badlog_result['count'] === null):
            echo 'Incorrect username/password. Please try again.';
            $msg = new Message;$msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Failed logon attempt by invalid user $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
            session_destroy();
            echo "<meta http-equiv='refresh' content='3;url=login.php'>";
            exit();
        else:
            echo 'Too many incorrect logins. Use the reset button to contact admin. Returning to login...';
            $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Too many incorrect logins from $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
            $obj = new UserStatus;$obj->TriggerLocked($email);
            session_destroy();
            echo "<meta http-equiv='refresh' content='5;url=login.php'>";
            exit();
        endif;
    else:
        echo 'Incorrect data submitted. Returning to login...';
        $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Failed logon attempt: Incorrect data sent from $email from {$_SERVER['REMOTE_ADDR']}",$logfile);
        session_destroy();
        echo "<meta http-equiv='refresh' content='5;url=login.php'>";
        exit();
    endif;
endif;
//Check if login has been successful
if ((isset($_SESSION["logged"])) AND ($_SESSION["logged"] == TRUE)) : 
    $msg = new Message;$msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"User $email logged in from {$_SERVER['REMOTE_ADDR']}",$logfile);
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
    echo "<meta http-equiv='refresh' content='2;url=index.php'>";
else:
    echo '<br><form action="login.php" method="post"><input type="hidden" name="ac" value="log"> '; 
    echo "<input class='textinput loginfield' type='email' name='email' autofocus placeholder='EMAIL'/>"; 
    echo "<br><br>";
    echo "<input class='textinput loginfield' type='password' name='password' placeholder='PASSWORD'/><br>"; 
    echo '<input type="submit" id="loginsubmit" value="LOGIN" />'; 
    echo '</form><br>'; 
?>
<div class='loginpagebutton'>
    <a href='reset.php'>RESET</a>
</div>
<?php endif; ?>
</div>
    
</body>
</html>