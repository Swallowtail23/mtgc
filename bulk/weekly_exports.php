<?php
/* Version:     1.0
    Date:       13/01/24
    Name:       weekly_exports.php
    Purpose:    Weekly collection exports
    Notes:      {none} 
        
    1.0         Initial release
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message;
$obj = new ImportExport($db,$logfile,$serveremail,$serveremail);

$list = '';
$usersExport = $db->execute_query("SELECT username, usernumber, email FROM users WHERE weeklyexport = 1");
while ($user = $usersExport->fetch_assoc()):
    $username = ucfirst($user['username']);
    $usertable = $user['usernumber']."collection";
    $useremail = $user['email'];
    $obj->exportCollectionToCsv($usertable, $myURL, $smtpParameters, 'weekly', 'export.csv', $username, $useremail);
    $list .= "$username ($useremail)\r\n";
endwhile;

$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$subject = "MtG Collection weekly export user report";
$emailbody = "Weekly collection export from MtG Collection have been run for:\r\n\r\n$list";
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $emailbody);
