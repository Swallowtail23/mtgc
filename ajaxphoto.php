<?php
/* Version:     1.1
   Date:        03/12/23
   Name:        ajaxphoto.php
   Purpose:     PHP script to import deck photo
   Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
   To do:       -

   1.1
                Refactored error handling using a variable and return
*/

session_start();
require('includes/ini.php');
require('includes/error_handling.php');
require('includes/functions_new.php');
include 'includes/colour.php';

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE):
    echo "<table class='ajaxshow'><tr><td class='name'>You are not logged in.</td></tr></table>";
    header("Refresh: 2; url=login.php"); // check if the user is logged in; else redirect to login.php
    exit();
else:
    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $useremail = str_replace("'", "", $_SESSION['useremail']);

    $response = ['success' => false, 'message' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST'):
        // Get the deck number from the form data
        $decknumber = isset($_POST['decknumber']) ? $_POST['decknumber'] : '';

        // Check if the file was uploaded without errors and it's a JPEG file
        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] === UPLOAD_ERR_OK &&
            $_FILES['photo']['type'] === 'image/jpeg'
        ):
            $deckPhotosDir = $ImgLocation . 'deck_photos/';

            // Create 'deck_photos' folder if it doesn't exist
            if (!file_exists($deckPhotosDir)):
                $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Creating 'deck_photos' folder in $ImgLocation", $logfile);

                if (!@mkdir($deckPhotosDir, 0755, true)):
                    $response['message'] = '<br>Failed to create directory for deck photos';
                    returnResponse();
                endif;
            endif;

            $uploadFile = $deckPhotosDir . $decknumber . '.jpg';

            // Check if the file size is greater than 1MB
            if ($_FILES['photo']['size'] > 1024 * 1024):
                $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Resizing $uploadFile using php-gd", $logfile);

                // Use GD to resize the image
                $uploadedImage = imagecreatefromjpeg($_FILES['photo']['tmp_name']);
                list($width, $height) = getimagesize($_FILES['photo']['tmp_name']);
                $newWidth = 800;
                $newHeight = ($newWidth / $width) * $height;
                $resizedImage = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

                if (!$resizedImage):
                    $response['message'] = 'Failed to resize the image using GD<br>';
                    returnResponse();
                endif;

                if (!imagecopyresampled($resizedImage, $uploadedImage, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, (int)$width, (int)$height) || !imagejpeg($resizedImage, $uploadFile, 80)):
                    // Handle the error, e.g., return an error response
                    $response['message'] = '<br>Failed to resize and save the image using GD';
                    returnResponse();
                endif;

                imagedestroy($uploadedImage);
                imagedestroy($resizedImage);
            else:
                $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Image $uploadFile does not need resizing", $logfile);

                // Move the uploaded file to the specified directory with the specific name
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)):
                    $response['message'] = 'Failed to move the uploaded file<br>';
                    returnResponse();
                endif;
            endif;
            $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Image upload success", $logfile);
            $response['success'] = true;
            $response['message'] = 'File is valid and was successfully uploaded<br>';
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Image upload failed", $logfile);
            $response['message'] = 'Invalid file or file upload error<br>';
        endif;
    endif;

    // Return the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
endif;

// Function to echo JSON response and exit
function returnResponse()
{
    global $response;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>