<?php
/* Version:     1.0
    Date:       02/12/23
    Name:       admin/ajaxsetimg.php
    Purpose:    Reload all images for a set
    Notes:      The page does not run standard secpagesetup as it breaks 
                the ajax login catch.
    To do:      -

    1.0
                Initial version
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('../includes/ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions_new.php');
include '../includes/colour.php';

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
    // Return an error in JSON format
    echo json_encode(["status" => "error", "message" => "You are not logged in."]);
    header("Refresh: 2; url=login.php"); // check if the user is logged in; else redirect to login.php
    exit(); 
else: 
    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'", "", $_SESSION['useremail']);
    
    if (isset($_POST['setcode'])):
        $setcode = $_POST['setcode'];
        $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Called with set $setcode", $logfile);
        
        $query = "SELECT id FROM cards_scry WHERE setcode = ?";
        $stmt = $db->prepare($query);

        if ($stmt):
            $stmt->bind_param("s", $setcode);
            $stmt->execute();
            $stmt->store_result();
            $obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Number of images to be refreshed in $setcode: " . $stmt->num_rows, $logfile);
            $stmt->bind_result($cardid);

            $iteration = 1;
            $fail_count = 0;
            $success_count = 0;
            $num_rows = $stmt->num_rows;
            while ($stmt->fetch()):
                $obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Image #$iteration/$num_rows", $logfile);
                $refresh_result = refresh_image($cardid);
                if($refresh_result === 'failure'):
                    $fail_count++;
                    $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Function 'refresh_image' failed",$logfile);
                else:
                    $success_count++;
                endif;
                $iteration++;
            endwhile;
            
            $stmt->free_result();
            $stmt->close();

            $db->close();
            $completediterations = $iteration - 1;
            $obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Processed $completediterations of $num_rows images for $setcode. Sucess: $success_count; Failed: $fail_count", $logfile);
            echo json_encode(["status" => "success", "message" => "Processed $completediterations of $num_rows images for $setcode. Sucess: $success_count; Failed: $fail_count"]);
        else:
            echo json_encode(["status" => "error", "message" => "SQL error"]);
            trigger_error('[ERROR] ajaxsetimg.php: Error: ' . $db->error, E_USER_ERROR);
        endif;
    else:
        echo json_encode(["status" => "error", "message" => "No setcode supplied"]);
        $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "No setcode supplied", $logfile);
    endif;
endif;
?>