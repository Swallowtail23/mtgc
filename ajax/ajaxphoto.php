<?php
/* Version:     1.2
   Date:        20/01/24
   Name:        ajax/ajaxphoto.php
   Purpose:     PHP script to import deck photo
   Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
   To do:       -

   1.1
                Refactored error handling using a variable and return
 
   1.2          20/01/24
 *              Include sessionname.php and move to logMessage
*/
require ('../includes/sessionname.php');
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
include '../includes/colour.php';
$msg = new Message($logfile);

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages =   [
                                $myURL . '/deckdetail.php'
                            ];

// Normalize the referring page URL
$normalizedReferringPage = str_replace('www.', '', $referringPage);

$isValidReferrer = false;
foreach ($expectedReferringPages as $page):
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false):
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer):

    if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== TRUE): 
        echo "<meta http-equiv='refresh' content='2;url=/login.php'>";               // check if user is logged in; else redirect to login.php
        exit(); 
    else: 
        // Need to run these as secpagesetup not run (see page notes)
        $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
        $userArray = $sessionManager->getUserInfo();
        $user = $userArray['usernumber'];
        $mytable = $userArray['table'];
        $useremail = $_SESSION['useremail'];

        $response = ['success' => false, 'message' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])):
            $msg->logMessage('[DEBUG]',"Called with 'update'");
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
                    $msg->logMessage('[DEBUG]',"Creating 'deck_photos' folder in $ImgLocation");

                    if (!@mkdir($deckPhotosDir, 0755, true)):
                        $response['message'] = '<br>Failed to create directory for deck photos';
                        returnResponse();
                    endif;
                else:
                    $msg->logMessage('[DEBUG]',"'deck_photos' folder already in $ImgLocation");
                endif;

                $uploadFile = $deckPhotosDir . $decknumber . '.jpg';

                // Check if the file size is greater than 1MB
                list($width, $height) = getimagesize($_FILES['photo']['tmp_name']);
                if ($width > 800 OR $height > 800):
                    $msg->logMessage('[DEBUG]',"Resizing $uploadFile using php-gd");

                    // Get EXIF data for orientation, and rotate if required
                    $exif = @exif_read_data($_FILES['photo']['tmp_name']);
                    $orientation = isset($exif['Orientation']) ? $exif['Orientation'] : 0;
                    $msg->logMessage('[DEBUG]',"EXIF orientation: $orientation");
                    if($orientation === 6):
                        $sourceCopy = imagecreatefromjpeg($_FILES['photo']['tmp_name']);
                        $rotatedImg = imagerotate($sourceCopy, -90, 0);
                        imagejpeg($rotatedImg, $_FILES['photo']['tmp_name']);
                    elseif($orientation === 3):
                        $sourceCopy = imagecreatefromjpeg($_FILES['photo']['tmp_name']);
                        $rotatedImg = imagerotate($sourceCopy, 180, 0);
                        imagejpeg($rotatedImg, $_FILES['photo']['tmp_name']);
                    elseif($orientation === 8):
                        $sourceCopy = imagecreatefromjpeg($_FILES['photo']['tmp_name']);
                        $rotatedImg = imagerotate($sourceCopy, 90, 0);
                        imagejpeg($rotatedImg, $_FILES['photo']['tmp_name']);
                    else:
                        // No orientation changes needed
                    endif;

                    // Assess new dimensions based on a maximum single length of 800px
                    list($width, $height) = getimagesize($_FILES['photo']['tmp_name']);
                    if($width > $height):
                        $newWidth = 800;
                        $newHeight = ($height / $width) * $newWidth;
                    elseif($height > $width):
                        $newHeight = 800;
                        $newWidth = ($width / $height) * $newHeight;
                    elseif($height == $width):
                        $newWidth = $newHeight = 800;
                    else:
                        $response['message'] = 'Failed to get image size<br>';
                        returnResponse();
                    endif;
                    $msg->logMessage('[DEBUG]',"Width: $width --> $newWidth, Height: $height --> $newHeight");

                    // Get the submitted file input, already rotated if needed
                    $uploadedImage = imagecreatefromjpeg($_FILES['photo']['tmp_name']);
                    // Resize it and write it
                    $resizedImage = imagecreatetruecolor((int)$newWidth, (int)$newHeight);
                    if (!imagecopyresampled($resizedImage, $uploadedImage, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, (int)$width, (int)$height) || !imagejpeg($resizedImage, $uploadFile, 80)):
                        $response['message'] = '<br>Failed to resize and save the image using GD';
                        returnResponse();
                    endif;
                    // Destroy temp files
                    imagedestroy($uploadedImage);
                    imagedestroy($resizedImage);
                else:
                    $msg->logMessage('[DEBUG]',"Image $uploadFile does not need resizing");

                    // Move the uploaded file to the specified directory with the specific name
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)):
                        $response['message'] = 'Failed to move the uploaded file<br>';
                        returnResponse();
                    endif;
                endif;
                $msg->logMessage('[DEBUG]',"Image upload success");
                $response['success'] = true;
                $response['message'] = 'File is valid and was successfully uploaded<br>';
            else:
                $msg->logMessage('[ERROR]',"Image upload failed");
                $response['message'] = 'Invalid file or file upload error<br>';
            endif;
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])):
            $msg->logMessage('[DEBUG]',"Called with 'delete'");
            $decknumber = isset($_POST['decknumber']) ? $_POST['decknumber'] : '';

            // Path to the file to be deleted
            $imageFilePath = $ImgLocation.'deck_photos/'.$decknumber.'.jpg';  //File path
            $existingImage = 'cardimg/deck_photos/'.$decknumber.'.jpg';       //Web path

            // Check if the file exists before attempting to delete
            if (file_exists($imageFilePath)):
                // Attempt to delete the file
                if (unlink($imageFilePath)):
                    $response['success'] = true;
                    $response['message'] = 'Image deleted successfully';
                else:
                    $response['message'] = 'Failed to delete the image';
                endif;
            else:
                $response['message'] = 'Image not found';
            endif;
        endif;

        // Return the response as JSON
        header('Content-Type: application/json');
        echo json_encode($response);
    endif;
else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from deckdetail.php");
    http_response_code(403);
    echo 'Access forbidden';
    exit();
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