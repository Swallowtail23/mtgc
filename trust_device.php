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
$redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'index.php';

$msg->logMessage('[DEBUG]', "Trust choice: $trust_choice, Redirect: $redirect_to");

// Process the trust device request
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
?>