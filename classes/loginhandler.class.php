<?php

/*
Version:     1.4
Date:        04/12/25
Name:        loginhandler.class.php
Purpose:     Encapsulate login handling logic for login.php
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
History:
    1.0 28/11/25 Initial version - extracted login handling flow from login.php
    1.1 28/11/25 Add injectable terminator for improved testability
    1.2 02/12/25 Render formatted page for login aborts
    1.3 02/12/25 Catch additional unformatted exits
    1.4 04/12/25 More robust handling of resets and sessions
*/

use andkab\Turnstile\Turnstile;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class LoginHandler
{
    private $db;
    private $logfile;
    private $message;
    private $turnstileEnabled;
    private $turnstileSecretKey;
    private $badLoginLimit;
    private $siteTitle;
    private $smtpParameters;
    private $serverEmail;
    private $terminator;

    public function __construct(
        $db,
        $logfile,
        $turnstileEnabled,
        $turnstileSecretKey,
        $badLoginLimit,
        $siteTitle,
        $smtpParameters,
        $serverEmail,
        $terminator = null
    ) {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
        $this->turnstileEnabled = $turnstileEnabled;
        $this->turnstileSecretKey = $turnstileSecretKey;
        $this->badLoginLimit = $badLoginLimit;
        $this->siteTitle = $siteTitle;
        $this->smtpParameters = $smtpParameters;
        $this->serverEmail = $serverEmail;
        $this->terminator = $terminator;
    }

    public function logStart()
    {
        $this->message->logMessage('[DEBUG]', 'Starting login.php execution. Checking for trusted device.');
    }

    public function attemptTrustedDeviceLogin($redirectUrl)
    {
        $result = [
        'trusted_login' => false,
        'redirect' => null
        ];

        if ($this->isLoggedIn()) :
            return $result;
        endif;

        $this->message->logMessage(
            '[DEBUG]',
            'Checking for trusted device cookie with db connection: ' . (isset($this->db) ? 'valid' : 'missing')
        );
        $deviceManager = new TrustedDeviceManager($this->db, $this->logfile);

        $trustedDeviceUser = $deviceManager->validateTrustedDevice();
        $trustedDeviceUser = (int) $trustedDeviceUser;
        $this->message->logMessage('[DEBUG]', "Output from Trusted device user check: $trustedDeviceUser");

        if ($trustedDeviceUser !== false) :
            $user_query = "SELECT usernumber, username, email, admin 
                            FROM users 
                            WHERE usernumber = ? AND status = 'active'
                          ";
            $stmt = $this->db->prepare($user_query);

            if ($stmt) :
                $userNumber = null;
                $userName = null;
                $userEmail = null;
                $admin = null;
                $stmt->bind_param("i", $trustedDeviceUser);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) :
                    $stmt->bind_result($userNumber, $userName, $userEmail, $admin);
                    $stmt->fetch();

                    $_SESSION['logged'] = true;
                    $_SESSION['user'] = $userNumber;
                    $_SESSION['useremail'] = $userEmail;
                    $_SESSION['admin'] = (bool) $admin;

                    $this->message->logMessage('[NOTICE]', "Auto-login via trusted device for user $userEmail");

                    if (!loginStamp($userEmail)) :
                        $this->message->logMessage(
                            '[ERROR]',
                            "Failed to update last login timestamp for $userEmail"
                        );
                    endif;

                    $result['trusted_login'] = true;
                    $redirectTarget = $redirectUrl ?? 'index.php';
                    $result['redirect'] = $redirectTarget;
                endif;

                $stmt->close();
            endif;
        endif;

        return $result;
    }

    public function renderAlreadyLoggedInPage($siteTitle, $cssver, $trustedLogin)
    {
        $message = $trustedLogin
            ? 'Welcome back! You\'ve been automatically signed in using a trusted device.'
            : 'You are already logged in!';
        echo "<meta http-equiv='refresh' content='2;url=index.php'>";
        echo "<div class='alert-box notice' style='margin:20px;'>" . htmlspecialchars($message) . "</div>";
        $this->terminate();
    }

    public function logPageLoad($post, $session)
    {
        $this->message->logMessage('[DEBUG]', 'Mid-load check: db=' . (isset($this->db) ? 'valid' : 'null'));

        $this->message->logMessage(
            '[DEBUG]',
            'Login.php loaded. POST vars: '
            . 'trust_device=' . ($post['trust_device'] ?? 'not set') . ', '
            . 'redirect_to=' . ($post['redirect_to'] ?? 'not set')
        );
        $this->message->logMessage(
            '[DEBUG]',
            'Session vars: '
            . 'logged=' . ($session['logged'] ?? 'not set') . ', '
            . 'user=' . ($session['user'] ?? 'not set') . ', '
            . 'useremail=' . ($session['useremail'] ?? 'not set')
        );
    }

    public function handleTurnstileCheck($post, $remoteAddress)
    {
        if ($this->turnstileEnabled !== 1 || !isset($post['cf-turnstile-response'])) :
            return;
        endif;

        $turnstileClient = new Turnstile("{$this->turnstileSecretKey}");
        $verifyResponse = $turnstileClient->verify($post['cf-turnstile-response'], $remoteAddress);
        if ($verifyResponse->isSuccess()) :
            $this->message->logMessage('[NOTICE]', "Cloudflare Turnstile success from {$remoteAddress}");
            return;
        endif;

        if ($verifyResponse->hasErrors()) :
            foreach ($verifyResponse->errorCodes as $errorCode) :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Cloudflare Turnstile failure $errorCode from {$remoteAddress}"
                );
            endforeach;
        else :
            $this->message->logMessage('[NOTICE]', "Cloudflare Turnstile failure (unknown) from {$remoteAddress}");
        endif;

        session_destroy();
        echo "<meta http-equiv='refresh' content='0;url=login.php?turnstilefail=yes'>";
        $this->terminate();
    }

    public function handleTurnstileFailureFlag($query)
    {
        if (!isset($query['turnstilefail']) || $query['turnstilefail'] !== "yes") :
            return;
        endif;

        echo '"Captcha" fail... Returning to login...';
        session_destroy();
        echo "<meta http-equiv='refresh' content='5;url=login.php'>";
        $this->terminate();
    }

    public function processLoginSubmission($post)
    {
        if (!$this->isLoginSubmission($post)) :
            return null;
        endif;

        if (!$this->hasCredentials($post)) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from {$post['email']} "
                . "from {$_SERVER['REMOTE_ADDR']} (email or password variables not set)"
            );
        endif;

        $rawEmail = $post['email'];
        $password = $post['password'];
        $email = filter_var((trim($rawEmail)), FILTER_SANITIZE_EMAIL);

        $this->message->logMessage('[NOTICE]', "Logon called for '$email' from {$_SERVER['REMOTE_ADDR']}");

        if (empty($email) || empty($password)) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from '$email' "
                . "from {$_SERVER['REMOTE_ADDR']} (email or password is empty)"
            );
        endif;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from '$email' "
                . "from {$_SERVER['REMOTE_ADDR']} (FILTER_VALIDATE_EMAIL failed)"
            );
        endif;

        $badLogin = new UserStatus($this->db, $this->logfile, $email);
        $badLoginResult = $badLogin->getBadLogin();
        if ($badLoginResult['count'] === null) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[ERROR]',
                "Failed logon attempt by invalid user $email from {$_SERVER['REMOTE_ADDR']}",
                3
            );
        endif;

        if ($badLoginResult['count'] >= $this->badLoginLimit) :
            $badLogin->triggerLocked();
            $this->abortLogin(
                'Your account is locked. Returning to login...',
                '[NOTICE]',
                "Too many incorrect logins from $email from {$_SERVER['REMOTE_ADDR']}"
            );
        endif;

        $passwordCheck = new PasswordCheck($this->db, $this->logfile, $this->siteTitle);
        $passwordResult = $passwordCheck->validatePassword($email, $password);
        if ($passwordResult !== 10) :
            $this->message->logMessage(
                '[ERROR]',
                "Failed logon attempt by valid user $email from {$_SERVER['REMOTE_ADDR']}"
            );
            $badLogin->incrementBadLogin();
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Password check failed for $email from {$_SERVER['REMOTE_ADDR']}",
                3
            );
        endif;

        $userStatus = new UserStatus($this->db, $this->logfile, $email);
        $userStatusResult = $userStatus->getUserStatus();
        $this->message->logMessage('[DEBUG]', "UserStatus for $email is {$userStatusResult['code']}");

        if ($userStatusResult['code'] === 0) :
            trigger_error("[ERROR] Login.php: user status check failure", E_USER_ERROR);
            return null;
        endif;

        if ($userStatusResult['code'] === 2) :
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Logon attempt for locked account $email from {$_SERVER['REMOTE_ADDR']}"
            );
        endif;

        if ($userStatusResult['code'] === 3) :
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Logon attempt for disabled account $email from {$_SERVER['REMOTE_ADDR']}"
            );
        endif;

        if ($userStatusResult['code'] !== 1 && $userStatusResult['code'] !== 10) :
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Failed logon attempt: Incorrect status for $email from {$_SERVER['REMOTE_ADDR']}"
            );
        endif;

        $tfaManager = new TwoFactorManager($this->db, $this->smtpParameters, $this->serverEmail, $this->logfile);
        if ($tfaManager->isEnabled($userStatusResult['number'])) :
            session_regenerate_id(true);
            $_SESSION['user_pending_2fa'] = $userStatusResult['number'];
            $_SESSION['useremail_pending_2fa'] = $email;
            $_SESSION['admin_pending_2fa'] = $userStatusResult['admin'] == 1;
            $_SESSION['chgpwd_pending_2fa'] = $userStatusResult['code'] === 1;

            if ($badLoginResult['count'] != 0) :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Logon (first factor) ok for $email, clearing non-zero "
                    . "bad login count ({$badLoginResult['count']})"
                );
                $zeroBadLogin = new UserStatus($this->db, $this->logfile, $email);
                $zeroBadLogin->zeroBadLogin();
            endif;

            if (isset($_SESSION['redirect_url'])) :
                $_SESSION['redirect_url_after_2fa'] = $_SESSION['redirect_url'];
            endif;

            $tfaManager->startVerification($userStatusResult['number'], $email);
            $this->message->logMessage(
                '[NOTICE]',
                "Password validated for $email, redirecting to 2FA verification"
            );
            header('Location: verify_2fa.php');
            $this->terminate();
        endif;

        $this->message->logMessage('[NOTICE]', 'Regenerating session ID after successful login');
        session_regenerate_id(true);
        $_SESSION['logged'] = true;
        $_SESSION['user'] = $userStatusResult['number'];
        $_SESSION['useremail'] = $email;

        if ($userStatusResult['code'] === 1) :
            $_SESSION['chgpwd'] = true;
            $_SESSION['just_logged_in'] = true;
        endif;

        $this->message->logMessage(
            '[NOTICE]',
            "Logon validated for $email from {$_SERVER['REMOTE_ADDR']}"
        );
        if ($badLoginResult['count'] != 0) :
            $this->message->logMessage(
                '[NOTICE]',
                "Logon ok for $email, clearing non-zero bad login "
                . "count ({$badLoginResult['count']})"
            );
            $zeroBadLogin = new UserStatus($this->db, $this->logfile, $email);
            $zeroBadLogin->zeroBadLogin();
        endif;

        return [
            'email' => $email,
            'usernumber' => $userStatusResult['number'],
            'userstat_result' => $userStatusResult
        ];
    }

    public function completeLogin($loginData, $redirectUrl)
    {
        if (!$this->isLoggedIn()) :
            return;
        endif;

        $email = $loginData['email'];
        $userNumber = $loginData['usernumber'];
        $userStatusResult = $loginData['userstat_result'];

        $this->message->logMessage('[NOTICE]', "User $email logged in from {$_SERVER['REMOTE_ADDR']}");

        if (!loginStamp($email)) :
            $this->message->logMessage('[ERROR]', "Failed to update last login timestamp for $email");
        endif;

        $mtceStatus = mtceModeCheck($userNumber);
        if ($mtceStatus == 1) :
            $noticeText = "Site is undergoing maintenance, please try again later...";
        else :
            $noticeText = $userStatusResult['admin'] == 1
                ? 'You are logged in!'
                : 'You are logged in';
            $_SESSION['admin'] = ($userStatusResult['admin'] == 1);
        endif;
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
    <title><?php echo htmlspecialchars($this->siteTitle ?? 'MTG Collection'); ?></title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo htmlspecialchars(cssVersionCheck());?>.css">
            <?php include 'includes/googlefonts.php'; ?>
</head>
<body id="loginbody" class="body">
    <div id="loginheader">
        <h2 id="h2"><?php echo htmlspecialchars($this->siteTitle ?? 'MTG Collection'); ?></h2>
        <div class='alert-box notice' style='margin:20px;'><?php echo htmlspecialchars($noticeText); ?></div>
    </div>
</body>
</html>
            <?php

            if (isset($mtceStatus) && $mtceStatus == 1) :
                $this->message->logMessage('[DEBUG]', "Mtce mode on; $email being redirected to login.php");
                session_destroy();
                echo "<meta http-equiv='refresh' content='2;url=login.php'>";
                $this->terminate();
            elseif (isset($_SESSION['chgpwd']) && $_SESSION['chgpwd'] === true) :
                $this->message->logMessage(
                    '[DEBUG]',
                    "User $email being redirected to profile.php for password change"
                );
                echo "<meta http-equiv='refresh' content='2;url=profile.php'>";
                $this->terminate();
            endif;

            $this->message->logMessage('[DEBUG]', 'Showing trust device prompt');
            header('Location: trust_device.php?redirect_to=' . urlencode($redirectUrl ?? 'index.php'));
            $this->terminate();
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['logged']) && $_SESSION['logged'] === true;
    }

    private function isLoginSubmission($post)
    {
        return isset($post['ac']) && $post['ac'] === "log";
    }

    private function hasCredentials($post)
    {
        return isset($post['password'], $post['email']);
    }

    private function abortLogin($message, $logLevel, $logMessage, $delaySeconds = 5)
    {
        $this->message->logMessage($logLevel, $logMessage);
        session_destroy();
        $this->renderLoginErrorPage($message, $delaySeconds);
        $this->terminate();
    }

    private function terminate($code = 0)
    {
        if (is_callable($this->terminator)) :
            call_user_func($this->terminator, $code);
            return;
        endif;
        exit($code);
    }

    private function renderLoginErrorPage($message, $delaySeconds)
    {
        $cssver = function_exists('cssVersionCheck') ? cssVersionCheck() : '';
        $safeTitle = htmlspecialchars($this->siteTitle);
        $safeMessage = htmlspecialchars($message);
        $delay = (int) $delaySeconds;
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta http-equiv='refresh' content='<?php echo $delay; ?>;url=login.php'>
    <meta
        name='viewport'
        content='initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no'
    >
    <title><?php echo $safeTitle; ?> - login</title>
    <link rel='manifest' href='/manifest.json' />
    <link rel='stylesheet' type='text/css' href='css/style<?php echo $cssver; ?>.css'>
        <?php include 'includes/googlefonts.php'; ?>
</head>
<body id='loginbody' class='body'>
    <div id='loginheader'>
        <h2 id='h2'><?php echo $safeTitle; ?></h2>
        <p><?php echo $safeMessage; ?></p>
    </div>
</body>
</html>
        <?php
    }
}
