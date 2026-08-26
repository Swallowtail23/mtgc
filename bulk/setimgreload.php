<?php

/*
Version:     1.28
Date:        26/08/26
Name:        setimgreload.php
Purpose:     Reload images for a set within an explicit language scope
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/


use MTG\Cards\ImageManager;
use MTG\Cards\SetImageReloadScope;
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

if (isset($argv[1], $argv[2])) :
    $setcode = $argv[1];
    $scope = $argv[2];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $setcode)) :
        $msg->logMessage('[ERROR]', "Invalid setcode supplied: '$setcode'");
        exit(1);
    endif;
    if (!SetImageReloadScope::isValid($scope)) :
        $msg->logMessage('[ERROR]', "Invalid set image reload scope supplied: '$scope'");
        exit(1);
    endif;
    $scopeLabel = SetImageReloadScope::label($scope);
    $msg->logMessage('[NOTICE]', "Called with set $setcode and scope $scope");

    $query = SetImageReloadScope::cardIdQuery($scope);
    $stmt = $db->prepare($query);

    if ($stmt) :
        $stmt->bind_param("s", $setcode);
        $stmt->execute();
        $stmt->store_result();
        $msg->logMessage(
            '[ERROR]',
            "Number of $scopeLabel images to be refreshed in $setcode: " . $stmt->num_rows
        );
        $cardId = '';
        $stmt->bind_result($cardId);
        $iteration = 1;
        $fail_count = 0;
        $success_count = 0;
        $num_rows = $stmt->num_rows;
        while ($stmt->fetch()) :
            $msg->logMessage('[DEBUG]', "Image #$iteration/$num_rows");
            $refresh_result = $obj->refreshImage($cardId);
            $refreshSuccess = false;
            if (is_array($refresh_result) && isset($refresh_result['success'])) :
                $refreshSuccess = (bool) $refresh_result['success'];
            elseif ($refresh_result === 'success') :
                $refreshSuccess = true;
            endif;

            if (!$refreshSuccess) :
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
            "Processed $completediterations of $num_rows $scopeLabel images for $setcode. Success: $success_count; "
            . "Failed: $fail_count"
        );

        // Email result
        $subject = "MTG $scopeLabel images reloaded for $setcode";
        $body = "Processed $completediterations of $num_rows $scopeLabel images for $setcode. "
            . "Success: $success_count; "
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
        throw new Exception('[ERROR] setimgreload.php: Error: ' . $db->error);
    endif;
else :
    $msg->logMessage('[ERROR]', "Not called with setcode and scope");
    exit(1);
endif;
