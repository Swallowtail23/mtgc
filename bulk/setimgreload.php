<?php

/*
Version:     1.6
Date:        21/12/25
Name:        setimgreload.php
Purpose:     Trigger reload all images for a set
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
To do:       -

History:
    1.0         Initial version
    1.1 13/01/24 Use PHPMailer for email report
    1.2 20/01/24 Move to logMessage
    1.3 25/11/25 Formatting clean-up
    1.4 25/11/25 Wrapped long log/email strings
    1.5 25/11/25 Rename PHPMailer wrapper to PascalCase
    1.6 21/12/25 Replace E_USER_ERROR trigger_error with exceptions for PHP 8.4 compatibility
*/

require('bulk_ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');

$msg = new Message($logfile);
$obj  = new ImageManager($db, $logfile, $serverEmail, $adminEmail);

if (isset($argv[1])) :
    $setcode = $argv[1];
    $msg->logMessage('[NOTICE]', "Called with set $setcode");

    $query = "SELECT id FROM cards_scry WHERE setcode = ?";
    $stmt = $db->prepare($query);

    if ($stmt) :
        $stmt->bind_param("s", $setcode);
        $stmt->execute();
        $stmt->store_result();
        $msg->logMessage('[ERROR]', "Number of images to be refreshed in $setcode: " . $stmt->num_rows);
        $stmt->bind_result($cardId);
        $iteration = 1;
        $fail_count = 0;
        $success_count = 0;
        $num_rows = $stmt->num_rows;
        while ($stmt->fetch()) :
            $msg->logMessage('[DEBUG]', "Image #$iteration/$num_rows");
            $refresh_result = $obj->refreshImage($cardId);
            if ($refresh_result === 'failure') :
                $fail_count++;
                $msg->logMessage('[ERROR]', "Function 'refreshImage' failed");
            else :
                $success_count++;
            endif;
            $iteration++;
        endwhile;
        $stmt->free_result();
        $stmt->close();
        $db->close();
        $completediterations = $iteration - 1;
        $msg->logMessage(
            '[DEBUG]',
            "Processed $completediterations of $num_rows images for $setcode. Success: $success_count; "
            . "Failed: $fail_count"
        );

        // Email result
        $subject = "MTG Images reloaded for $setcode";
        $body = "Processed $completediterations of $num_rows images for $setcode. Success: $success_count; "
            . "Failed: $fail_count";
        if (isset($emailEnabled) && $emailEnabled === true) :
            $mail = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
            $mailresult = $mail->sendEmail($adminEmail, false, $subject, $body);
        else :
            $msg->logMessage(
                '[NOTICE]',
                "Email disabled; set image reload alert not sent for $setcode"
            );
            $mailresult = false;
        endif;
        $msg->logMessage('[DEBUG]', "Mail result is '$mailresult'");
    else :
        echo json_encode(["status" => "error", "message" => "SQL error"]);
        throw new Exception('[ERROR] ajaxsetimg.php: Error: ' . $db->error);
    endif;
else :
    $msg->logMessage('[ERROR]', "Not called with setcode");
    exit;
endif;
