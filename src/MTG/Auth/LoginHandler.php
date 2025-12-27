<?php

/*
Version:     1.8
Date:        27/12/25
Name:        LoginHandler.php
Purpose:     Encapsulate login handling logic for login.php
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

Current flow:
- Check it’s a login submission.
- Check credentials exist (hasCredentials()).
- Sanitize + validate email.
- Create UserStatus once ($user).
- Get bad login count, reject if user not found.
- Get user status once, handle locked/disabled/invalid statuses.
- Validate password.
- If bad password:
-- Increment bad login.
-- If threshold reached, lock + notify.
-- Abort with generic failure.
- If password OK:
-- If 2FA enabled → set pending 2FA session, clear bad login if needed, redirect to verify_2fa.
-- Else → mark logged in, clear bad login if needed, return user info.
    1.8 21/12/25 Replace E_USER_ERROR trigger_error with exceptions for PHP 8.4 compatibility
*/

namespace MTG\Auth;

use andkab\Turnstile\Turnstile;

class LoginHandler
{
    /**
    * @var mysqli
    */
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
        $this->message = new \MTG\Core\Message($this->logfile);
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
        $deviceManager = new \MTG\Auth\TrustedDeviceManager($this->db, $this->logfile);

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
        $siteTitleEsc = htmlspecialchars((string) $siteTitle, ENT_QUOTES, 'UTF-8');
        $cssverEsc = htmlspecialchars((string) $cssver, ENT_QUOTES, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo "<!DOCTYPE html>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='initial-scale=1.1, maximum-scale=1.1, "
            . "minimum-scale=1.1, user-scalable=no'>";
        echo "<title>{$siteTitleEsc} - login</title>";
        echo "<link rel='manifest' href='/manifest.json' />";
        echo "<link rel='stylesheet' type='text/css' href='css/style{$cssverEsc}.css'>";
        include 'includes/googlefonts.php';
        echo "<meta http-equiv='refresh' content='2;url=index.php'>";
        echo "</head>";
        echo "<body id='loginbody' class='body'>";
        include_once 'includes/analyticstracking.php';
        echo "<div id='loginheader'>";
        echo "<h2 id='h2'>{$siteTitleEsc}</h2>";
        echo "<div class='alert-box notice' style='margin:20px;'>{$messageEsc}</div>";
        echo "</div>";
        echo "</body>";
        echo "</html>";
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        if (!$this->hasCredentials($post)) :
            $emailForLog = isset($post['email']) ? $post['email'] : 'UNKNOWN';
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from {$emailForLog} "
                . "from $ip (email or password variables not set)"
            );
        endif;

        $rawEmail = $post['email'];
        $password = $post['password'];
        $email = filter_var((trim($rawEmail)), FILTER_SANITIZE_EMAIL);

        $this->message->logMessage('[NOTICE]', "Logon called for '$email' from $ip");

        if (empty($email) || empty($password)) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from '$email' "
                . "from $ip (email or password is empty)"
            );
        endif;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) :
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Failed logon attempt: Incorrect data sent from '$email' "
                . "from $ip (FILTER_VALIDATE_EMAIL failed)"
            );
        endif;

        // Create once — reuse for everything
        $user = new \MTG\Auth\UserStatus($this->db, $this->logfile, $email);

        // Check bad login count
        $badLoginResult = $user->getBadLogin();

        if (
            !is_array($badLoginResult)
            ||
            !array_key_exists('count', $badLoginResult)
            ||
            $badLoginResult['count'] === null
        ) :
            // No such user – generic failure
            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[ERROR]',
                "Failed logon attempt by invalid user $email from $ip",
                3
            );
        endif;
        $badCount = (int) $badLoginResult['count'];

        // Get current user status once and re-use it
        $userStatusResult = $user->getUserStatus();
        if (
            !is_array($userStatusResult)
            ||
            !array_key_exists('code', $userStatusResult)
            ||
            !array_key_exists('number', $userStatusResult)
            ||
            !array_key_exists('admin', $userStatusResult)
        ) :
            throw new \Exception("[ERROR] Login.php: user status structure invalid");
            return null;
        endif;
        $code  = (int) $userStatusResult['code'];
        $id    = (int) $userStatusResult['number'];
        $admin = (int) $userStatusResult['admin'];
        $this->message->logMessage('[DEBUG]', "UserStatus for $email is {$code}");
        if ($code === 0) : // An error has been returned - fail.
            throw new \Exception("[ERROR] Login.php: user status check failure");
            return null;
        endif;

        // If account is already locked or disabled, block immediately
        if ($code === 2) :
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Logon attempt for locked account $email from $ip"
            );
        endif;

        if ($code === 3) :
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Logon attempt for disabled account $email from $ip"
            );
        endif;

        if ($code !== 1 && $code !== 10) : // Anything else, not right
            $this->abortLogin(
                'There is a problem with your account. Contact the administrator. Returning to login...',
                '[ERROR]',
                "Failed logon attempt: Incorrect status for $email from $ip"
            );
        endif;

        // At this point, account is in a "normal" status; now we check password
        $passwordCheck = new \MTG\Auth\PasswordCheck($this->db, $this->logfile, $this->siteTitle);
        $passwordResult = $passwordCheck->validatePassword($email, $password);

        if ($passwordResult !== 10) :
            $this->message->logMessage(
                '[ERROR]',
                "Failed logon attempt by valid user $email from $ip"
            );
            $user->incrementBadLogin();
            $currentCount = $badCount + 1;

            if ($currentCount >= $this->badLoginLimit) :
                $user->triggerLocked();
                $this->sendLockNotification($email);
                $this->abortLogin(
                    'Incorrect username or password submitted. Returning to login...',
                    '[NOTICE]',
                    "Too many incorrect logins from $email from $ip"
                );
            endif;

            $this->abortLogin(
                'Incorrect username or password submitted. Returning to login...',
                '[NOTICE]',
                "Password check failed for $email from $ip",
                3
            );
        endif;

        $tfaManager = new \MTG\Auth\TwoFactorManager(
            $this->db,
            $this->smtpParameters,
            $this->serverEmail,
            $this->logfile
        );
        if ($tfaManager->isEnabled($id)) :
            session_regenerate_id(true);
            $_SESSION['user_pending_2fa'] = $id;
            $_SESSION['useremail_pending_2fa'] = $email;
            $_SESSION['admin_pending_2fa'] = ($admin === 1);
            $_SESSION['chgpwd_pending_2fa'] = ($code === 1);

            if ($badCount != 0) :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Logon (first factor) ok for $email, clearing non-zero "
                    . "bad login count ({$badCount})"
                );
                $user->zeroBadLogin();
            endif;

            if (isset($_SESSION['redirect_url'])) :
                $_SESSION['redirect_url_after_2fa'] = $_SESSION['redirect_url'];
            endif;

            $tfaManager->startVerification($id, $email);
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
        $_SESSION['user'] = $id;
        $_SESSION['useremail'] = $email;

        if ($code === 1) :
            $_SESSION['chgpwd'] = true;
            $_SESSION['just_logged_in'] = true;
        endif;

        $this->message->logMessage(
            '[NOTICE]',
            "Logon validated for $email from $ip"
        );
        if ($badCount != 0) :
            $this->message->logMessage(
                '[NOTICE]',
                "Logon ok for $email, clearing non-zero bad login "
                . "count ({$badCount})"
            );
            $user->zeroBadLogin();
        endif;

        return [
            'email' => $email,
            'usernumber' => $id,
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
            $noticeText = $userStatusResult['admin'] === 1
                ? 'You are logged in!'
                : 'You are logged in';
            $_SESSION['admin'] = ($userStatusResult['admin'] === 1);
        endif;
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
    <title><?php echo htmlspecialchars($this->siteTitle ?? 'MTG Collection', ENT_QUOTES, 'UTF-8'); ?></title>
    <link 
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars(cssVersionCheck(), ENT_QUOTES, 'UTF-8');?>.css"
    >
        <?php include 'includes/googlefonts.php'; ?>
</head>
<body id="loginbody" class="body">
    <div id="loginheader">
        <h2 id="h2"><?php echo htmlspecialchars($this->siteTitle ?? 'MTG Collection', ENT_QUOTES, 'UTF-8'); ?></h2>
        <div class='alert-box notice' style='margin:20px;'>
            <?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?>
        </div>
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
            $_SESSION['trust_device_flow'] = true;
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
        $redirectUrl = null;
        if (isset($_SESSION['redirect_url'])) :
            $redirectUrl = normalizeRedirectUrl($_SESSION['redirect_url']);
            if ($redirectUrl) :
                $this->message->logMessage('[DEBUG]', "Preserving redirect URL on login abort: $redirectUrl");
            endif;
        endif;
        session_destroy();
        $this->renderLoginErrorPage($message, $delaySeconds, $redirectUrl);
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

    private function renderLoginErrorPage($message, $delaySeconds, $redirectUrl = null)
    {
        $cssver = function_exists('cssVersionCheck') ? cssVersionCheck() : '';
        $safeTitle = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $delay = (int) $delaySeconds;
        $redirectTarget = 'login.php';
        if ($redirectUrl) :
            $redirectTarget = 'login.php?redirect_to=' . urlencode($redirectUrl);
        endif;
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta http-equiv='refresh' content='<?php echo $delay; ?>;url=<?php echo $redirectTarget; ?>'>
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

    private function sendLockNotification($email)
    {
        global $myURL, $adminEmail;
        if (!isset($GLOBALS['emailEnabled']) || $GLOBALS['emailEnabled'] !== true) :
            $this->message->logMessage('[NOTICE]', "Lock notice suppressed; email disabled for $email");
            return false;
        endif;
        if (!class_exists(\MTG\Core\MyPHPMailer::class)) :
            $this->message->logMessage(
                '[ERROR]',
                "Lock notice failed; MyPHPMailer not available for $email"
            );
            return false;
        endif;

        $resetLink = (isset($myURL) && $myURL !== '')
            ? rtrim($myURL, '/') . '/reset.php'
            : '/reset.php';
        $subject = "{$this->siteTitle} account locked";
        $plain = "Your account on {$this->siteTitle} has been locked after too many incorrect logins.\n"
               . "If this was not you, please reset your password here: {$resetLink}\n"
               . "Alternatively, contact the administrator.\n"
               . "Resetting your password will unlock your account.";
        $html = "<p>Your account on {$this->siteTitle} has been locked after too many incorrect logins.</p>"
              . "<p>If this was not you, please <a href='{$resetLink}'>reset your password</a> "
              . "or contact the administrator.</p>"
              . "<p>Resetting your password will unlock your account.</p>";

        $mailer = new \MTG\Core\MyPHPMailer(
            true,
            $this->smtpParameters,
            $this->serverEmail,
            $this->logfile,
            $this->siteTitle
        );
        if (isset($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) :
            $mailer->addCC($adminEmail);
        else :
            $this->message->logMessage('[NOTICE]', "Admin CC skipped; invalid adminEmail: " . ($adminEmail ?? 'unset'));
        endif;
        if ($mailer->sendEmail($email, true, $subject, $html, $plain)) :
            $this->message->logMessage('[NOTICE]', "Lock notice sent to $email");
            return true;
        endif;
        $this->message->logMessage('[ERROR]', "Lock notice failed to send to $email");
        return false;
    }
}
