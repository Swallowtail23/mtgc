<?php
/* Version:     1.2
    Date:       20/01/24
    Name:       ajax/ajaxsetimg.php
    Purpose:    Trigger reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 
 *  1.1         13/01/24
 *              Use PHPMailer for email report
 
 *  1.2         20/01/24
 *              Move to logMessage
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');

$msg = new Message($logfile);
$obj  = new ImageManager($db, $logfile, $serveremail, $adminemail);

if(isset($argv[1])):
    $setcode = $argv[1];
    $msg->logMessage('[NOTICE]',"Called with set $setcode");

    $query = "SELECT id FROM cards_scry WHERE setcode = ?";
    $stmt = $db->prepare($query);

    if ($stmt):
        $stmt->bind_param("s", $setcode);
        $stmt->execute();
        $stmt->store_result();
        $msg->logMessage('[ERROR]',"Number of images to be refreshed in $setcode: " . $stmt->num_rows);
        $stmt->bind_result($cardid);
        $iteration = 1;
        $fail_count = 0;
        $success_count = 0;
        $num_rows = $stmt->num_rows;
        while ($stmt->fetch()):
            $msg->logMessage('[DEBUG]',"Image #$iteration/$num_rows");
            $refresh_result = $obj->refreshImage($cardid);
            if($refresh_result === 'failure'):
                $fail_count++;
                $msg->logMessage('[ERROR]',"Function 'refreshImage' failed");
            else:
                $success_count++;
            endif;
            $iteration++;
        endwhile;
        $stmt->free_result();
        $stmt->close();
        $db->close();
        $completediterations = $iteration - 1;
        $msg->logMessage('[DEBUG]',"Processed $completediterations of $num_rows images for $setcode. Success: $success_count; Failed: $fail_count");
        
        // Email result
        $subject = "MTG Images reloaded for $setcode";
        $body = "Processed $completediterations of $num_rows images for $setcode. Success: $success_count; Failed: $fail_count";
        $mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
        $mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
        $msg->logMessage('[DEBUG]',"Mail result is '$mailresult'");
    else:
        echo json_encode(["status" => "error", "message" => "SQL error"]);
        trigger_error('[ERROR] ajaxsetimg.php: Error: ' . $db->error, E_USER_ERROR);
    endif;
else:
    $msg->logMessage('[ERROR]',"Not called with setcode");
    exit;
endif;
?>