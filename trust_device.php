<?php
/* Version:     1.2
    Date:       01/03/25
    Name:       trust_device.php
    Purpose:    Handle trusted device creation separately from the login flow
    
    @author     Simon Wilson
    @copyright  2025 MTG Collection

    Notes:      
    To do:      
    
    1.0
                Initial version
    1.1
                - Added session_regenerate_id() for security
                - Added CSRF token validation
                - Ensured $db is valid before using it
                - Unset redirect session variable after use
                - Typecasted $_SESSION["user"] to prevent issues

    1.2         01/03/25
                Code tidy and consistency tweaks
*/

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();

// ** Prevent session fixation attacks **
session_regenerate_id(true);

// Only proceed if user is logged in
if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== TRUE) {
    header("Location: login.php");
    exit();
}

// Load required files
require_once('includes/ini.php');               // Include ini file
require_once('includes/error_handling.php');    // Include error handler
require_once('includes/functions.php');         // Include needed functions

// Initialize message object for logging
$msg = new Message($logfile);

// Get CSS Version
$cssver = cssver();

// Ensure `$db` is valid before proceeding
if (!isset($db) || !$db instanceof mysqli) {
    $msg->logMessage('[ERROR]', "Database connection is invalid in trust_device.php");
    die("A database error occurred, please try again later");
}

// Generate CSRF token if not set
if (!isset($_SESSION["csrf_token"])):
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
endif;

// Check for CSRF token
if ($_SERVER["REQUEST_METHOD"] === "POST"):
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]):
        $msg->logMessage('[ERROR]', "CSRF token mismatch in trust_device.php");
        die("Invalid request.");
    endif;
endif;

// Get the trust choice from form submission
$trust_choice = $_POST['trust_device'] ?? 'none';

// Get redirect URL from POST, GET, or session
$redirect_to = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? $_SESSION['redirect_url'] ?? 'index.php';

// ** Prevent redirect loop to login.php **
if ($redirect_to === 'login.php'):
    $redirect_to = 'index.php';
endif;

// ** Unset session redirect variable after use **
unset($_SESSION['redirect_url']);

// Prevent unnecessary doubled up log entries
// Only log these details for POST requests, since GET is just first load and display of form
if ($_SERVER["REQUEST_METHOD"] === "POST") :
    $msg->logMessage('[DEBUG]', "Final redirect destination (POST): $redirect_to");
    $msg->logMessage('[DEBUG]', "Trust choice (POST): $trust_choice, Redirect: $redirect_to");
endif;

// ** Process form submission **
if ($trust_choice !== 'none'):
    if ($trust_choice === 'yes'):
        // User wants to trust this device

        try {
            $user_id = (int) $_SESSION["user"]; // ** Ensure user ID is integer**
            $msg->logMessage('[DEBUG]', "Creating trusted device for user $user_id");
            $deviceManager = new TrustedDeviceManager($db, $logfile);
            $result = $deviceManager->createTrustedDevice($user_id, $trustDuration); // Set trust
            $msg->logMessage('[NOTICE]', "User {$_SESSION["useremail"]} trusted device result: " . ($result ? 'success' : 'failed'));
        } catch (Exception $e) {
            $msg->logMessage('[ERROR]', "Failed to create trusted device: " . $e->getMessage());
        }
    else:
        $msg->logMessage('[DEBUG]', "User chose not to trust this device");
    endif;
    
    // Redirect to the appropriate page
    $msg->logMessage('[DEBUG]', "Redirecting to $redirect_to");
    header("Location: $redirect_to");
    exit();
else:
    // This is not a form submission, so display the trust device prompt
    $msg->logMessage('[DEBUG]', "Trust choice not yet set, display the trust form");
    ?>
    <!DOCTYPE html>
        <head>
            <title><?php echo $siteTitle;?> - Trust Device</title>
            <link rel="manifest" href="manifest.json" />
            <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
            <?php include('includes/googlefonts.php'); ?>
            <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
        </head>
        <body id="loginbody" class="body">
            <?php include_once("includes/analyticstracking.php") ?>
            <div id="loginheader">
                <h2 id='h2'><?php echo $siteTitle;?></h2>
                    <p>You are logged in<?php echo isset($_SESSION['admin']) && $_SESSION['admin'] ? "!" : ""; ?></p>
                <div id="trust-device-prompt" style="text-align: center; margin-top: 20px;">
                    <form action="trust_device.php" method="post">
                        <p>Would you like to trust this device for <?php echo $trustDuration; ?> days?</p>
                        <p><small>Clicking the site's logout button will cancel this device trust</small></p>

                        <input type="hidden" name="trust_device" value="yes">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">

                        <button type="submit" class="profilebutton" style="background-color: #4CAF50; margin-right: 10px;">TRUST</button>
                    </form>

                    <form action="trust_device.php" method="post" style="margin-top: 10px;">
                        <input type="hidden" name="trust_device" value="no">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">

                        <button type="submit" class="profilebutton" style="margin-right: 10px;">NOT NOW</button>
                    </form>
                </div>
            </div>
        </body>
    </html>
<?php
endif;
?>
