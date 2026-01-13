<?php

/*
Version:     1.25
Date:        13/01/26
Name:        setimgreload.php
Purpose:     Trigger reload all images for a set
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/


use MTG\Cards\ImageManager;
use MTG\Core\MyPHPMailer;

// Bootstrap
$ctx                = require __DIR__ . '/bulk_ini.php';

$appConfig          = $ctx->config();
$db                 = $ctx->db();
$msg                = $ctx->message();
$gameRules          = $ctx->rules();

$adminEmail         = (string) $appConfig->email('adminEmail', '');
$emailEnabled       = (bool) $appConfig->email('enabled', false);

// Content
$obj  = new ImageManager($db, $appConfig, $gameRules);

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
            $mail = new MyPHPMailer(true, $appConfig);
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
