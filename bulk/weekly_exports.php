<?php
/* Version:     1.1
    Date:       20/01/24
    Name:       weekly_exports.php
    Purpose:    Weekly collection exports
    Notes:      Exports csv card collections where users are active and have opted in
        
    1.0         Initial release
 * 
 *  1.1         20/01/24
 *              Added requirement to be 'active' status
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg = new Message($logfile);
$obj = new ImportExport($db,$logfile,$serveremail,$serveremail,$siteTitle);

$list = '';
$usersExport = $db->execute_query("SELECT username, usernumber, email, status FROM users WHERE weeklyexport = 1 AND status = 'active'");
while ($user = $usersExport->fetch_assoc()):
    $username = ucfirst($user['username']);
    $usertable = $user['usernumber']."collection";
    $useremail = $user['email'];
    $obj->exportCollectionToCsv($usertable, $myURL, $smtpParameters, 'weekly', 'export.csv', $username, $useremail);
    $list .= "$username ($useremail)\r\n";
endwhile;

$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$subject = "$siteTitle weekly export user report";
$emailbody = "Weekly collection export from $siteTitle have been run for:\r\n\r\n$list";
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $emailbody);
