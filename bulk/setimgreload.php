<?php
/* Version:     1.1
    Date:       13/01/24
    Name:       ajax/ajaxsetimg.php
    Purpose:    Trigger reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
 
 *  1.1         13/01/24
 *              Use PHPMailer for email report
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');

$msg  = new Message;
$obj  = new ImageManager($db, $logfile, $serveremail, $adminemail);

if(isset($argv[1])):
    $setcode = $argv[1];
    $msg->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Called with set $setcode", $logfile);

    $query = "SELECT id FROM cards_scry WHERE setcode = ?";
    $stmt = $db->prepare($query);

    if ($stmt):
        $stmt->bind_param("s", $setcode);
        $stmt->execute();
        $stmt->store_result();
        $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Number of images to be refreshed in $setcode: " . $stmt->num_rows, $logfile);
        $stmt->bind_result($cardid);
        $iteration = 1;
        $fail_count = 0;
        $success_count = 0;
        $num_rows = $stmt->num_rows;
        while ($stmt->fetch()):
            $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Image #$iteration/$num_rows", $logfile);
            $refresh_result = $obj->refreshImage($cardid);
            if($refresh_result === 'failure'):
                $fail_count++;
                $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Function 'refreshImage' failed",$logfile);
            else:
                $success_count++;
            endif;
            $iteration++;
        endwhile;
        $stmt->free_result();
        $stmt->close();
        $db->close();
        $completediterations = $iteration - 1;
        $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Processed $completediterations of $num_rows images for $setcode. Success: $success_count; Failed: $fail_count", $logfile);
        
        // Email result
        $subject = "MTG Images reloaded for $setcode";
        $body = "Processed $completediterations of $num_rows images for $setcode. Success: $success_count; Failed: $fail_count";
        $mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
        $mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
        $msg->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Mail result is '$mailresult'",$logfile);
    else:
        echo json_encode(["status" => "error", "message" => "SQL error"]);
        trigger_error('[ERROR] ajaxsetimg.php: Error: ' . $db->error, E_USER_ERROR);
    endif;
else:
    $msg->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Not called with setcode", $logfile);
    exit;
endif;
?>