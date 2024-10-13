<?php
/* Version:     2.0
    Date:       08/09/24
    Name:       weekly_exports.php
    Purpose:    Weekly collection exports
    Notes:      Exports csv card collections where users are active and have opted in
        
    1.0         Initial release
 * 
 *  1.1         20/01/24
 *              Added requirement to be 'active' status
 * 
 *  2.0         08/09/24
 *              MTGC-125 - adding decks to exports
*/

require ('bulk_ini.php');
require ('../includes/error_handling.php');
require ('../includes/functions.php');
$msg   = new Message($logfile);
$obj   = new ImportExport($db,$logfile,$serveremail,$serveremail,$siteTitle);
$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);

$list = '';
$usersExport = $db->execute_query("SELECT username, usernumber, email, status FROM users WHERE weeklyexport = 1 AND status = 'active'");
while ($user = $usersExport->fetch_assoc()):
    $username = ucfirst($user['username']);
    $usernumber = $user['usernumber'];
    $usertable = $usernumber."collection";
    $useremail = $user['email'];
    $decks = new DeckManager($db, $logfile, $useremail, $serveremail, $importLinestoIgnore, $nonPreferredSetCodes);
    // Decks
    $query = 'SELECT decknumber FROM decks WHERE owner=?';
    $stmt = $db->execute_query($query, [$usernumber]);
    if ($stmt === FALSE):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    elseif ($stmt->num_rows < 1):
        $msg->logMessage('[ERROR]',"No decks for user '$useremail'");
    else:
        $qtyDecks = $stmt->num_rows;
        $msg->logMessage('[DEBUG]',"$qtyDecks decks for user '$useremail'");
        $decksProcessed = 0;
        while ($deckrow = $stmt->fetch_assoc()):
            $decknumber = $deckrow['decknumber'];
            if ($decksProcessed === 0):
                $decksProcessed = $decksProcessed + 1;
                $msg->logMessage('[DEBUG]',"Processing deck $decksProcessed/$qtyDecks");
                $zipFilePath = $decks->exportDeck($decknumber,"bulk");
                if($zipFilePath === FALSE):
                    $msg->logMessage('[ERROR]',"Error returned from deckManager");
                    exit;
                endif;
            else:
                $decksProcessed = $decksProcessed + 1;
                $msg->logMessage('[DEBUG]',"Processing deck $decksProcessed/$qtyDecks");
                $addnext = $decks->exportDeck($decknumber,"bulk",$zipFilePath);
                if($addnext === FALSE):
                    $msg->logMessage('[ERROR]',"Error returned from deckManager");
                    exit;
                endif;
            endif;
        endwhile;
        $subject = "$siteTitle weekly decks export";
        $emailbody = "Hi $username, please see attached your weekly decks export from $siteTitle. <br><br> Opt out of automated emails in your profile at <a href='$myURL/profile.php'>your $siteTitle profile page</a>";
        $emailaltbody = "Hi $username, please see attached your weekly decks export from $siteTitle. \r\n\r\n Opt out of automated emails in your profile at your $siteTitle profile page ($myURL/profile.php) \r\n\r\n";
        $mailresult = $mail->sendEmail($useremail, TRUE, $subject, $emailbody, $emailaltbody, $zipFilePath);
        if (isset($zipFilePath)):
            unlink($zipFilePath);
        endif;
    endif;
    
    // Collection
    $obj->exportCollectionToCsv($usertable, $myURL, $smtpParameters, 'weekly', 'export.csv', $username, $useremail);
    $list .= "$username ($useremail)\r\n";
endwhile;

$subject = "$siteTitle weekly export user report";
$emailbody = "Weekly collection export from $siteTitle have been run for:\r\n\r\n$list";
$mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
$mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $emailbody);
