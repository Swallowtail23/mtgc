<?php
/* Version:     1.0
    Date:       28/02/25
    Name:       trust_device.php
    Purpose:    Handle trusted device creation separately from the login flow
    Notes:      
    To do:      
    
    1.0
                Initial version
*/

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();

// Only proceed if user is logged in
if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== TRUE) {
    header("Location: login.php");
    exit();
}

// Load required files
require ('includes/ini.php');
require ('includes/error_handling.php');
require ('includes/functions.php');

// Initialize logging
$msg = new Message($logfile);
$msg->logMessage('[DEBUG]', "trust_device.php loaded. Session user: " . $_SESSION["user"]);

// Check if this is a form submission
$trust_choice = isset($_POST['trust_device']) ? $_POST['trust_device'] : 'none';

// Get redirect URL from POST or GET parameters
$redirect_to = 'index.php'; // Default
if (isset($_POST['redirect_to'])):
    $redirect_to = $_POST['redirect_to'];
elseif (isset($_GET['redirect_to'])):
    $redirect_to = $_GET['redirect_to'];
endif;

$msg->logMessage('[DEBUG]', "Trust choice: $trust_choice, Redirect: $redirect_to");

// If this is a form submission, process it
if ($trust_choice !== 'none'):
    if ($trust_choice === 'yes'):
        // User wants to trust this device
        require_once('classes/trusteddevicemanager.class.php');
        
        try {
            $msg->logMessage('[DEBUG]', "Creating trusted device for user {$_SESSION["user"]}");
            $deviceManager = new TrustedDeviceManager($db, $logfile);
            $result = $deviceManager->createTrustedDevice($_SESSION["user"], 7); // Trust for 7 days
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
    $cssver = cssver();
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
        <div style="text-align: center; margin-bottom: 20px;">
            <p>You are logged in!</p>
        </div>
        
        <div id="trust-device-prompt" style="text-align: center; margin-top: 20px;">
            <form action="trust_device.php" method="post">
                <p>Would you like to stay logged in on this device?</p>
                <p><small>This will keep you signed in for 7 days</small></p>
                
                <input type="hidden" name="trust_device" value="yes">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
                
                <button type="submit" class="profilebutton" style="background-color: #4CAF50; margin-right: 10px;">Trust device</button>
            </form>
            
            <form action="trust_device.php" method="post" style="margin-top: 10px;">
                <input type="hidden" name="trust_device" value="no">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
                
                <button type="submit" class="profilebutton" style="margin-right: 10px;">Not now</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
endif;
?>
