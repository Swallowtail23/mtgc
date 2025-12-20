<?php

/*
Version:     2.6
Date:        20/12/25
Name:        weekly_exports.php
Purpose:     Weekly collection exports
Notes:       Exports csv card collections where users are active and have opted in

History:
    1.0         Initial release
    1.1 20/01/24 Added requirement to be 'active' status
    2.0 08/09/24 MTGC-125 - adding decks to exports
    2.1 25/11/25 Formatting clean-up
    2.2 25/11/25 Wrapped long SQL/email strings
    2.3 25/11/25 Rename PHPMailer wrapper to PascalCase
    2.4 20/12/25 Add weekly value history export
    2.5 20/12/25 Attach value history CSV to weekly collection export email
    2.6 20/12/25 Consolidate weekly exports into a single email
*/

require('bulk_ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg   = new Message($logfile);
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
$obj   = new ImportExport($db, $logfile, $serverEmail, $serverEmail, $siteTitleEsc);
$historyExporter = new CollectionHistory($db, $logfile, $siteTitleEsc);

$list = '';
$usersExport = $db->execute_query(
    "SELECT username, usernumber, email, status FROM users WHERE weeklyexport = 1 AND status = 'active'"
);
while ($user = $usersExport->fetch_assoc()) :
    $userName = ucfirst($user['username']);
    $userNumber = $user['usernumber'];
    $usertable = $userNumber . "collection";
    $userEmail = $user['email'];
    $decks = new DeckManager($db, $logfile, $userEmail, $serverEmail, $importLinestoIgnore, $nonPreferredSetCodes);
    // Decks
    $deckZipPath = '';
    $query = 'SELECT decknumber FROM decks WHERE owner=?';
    $stmt = $db->execute_query($query, [$userNumber]);
    if ($stmt === false) :
        trigger_error(
            '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: "
            . $db->error,
            E_USER_ERROR
        );
    elseif ($stmt->num_rows < 1) :
        $msg->logMessage('[ERROR]', "No decks for user '$userEmail'");
    else :
        $qtyDecks = $stmt->num_rows;
        $msg->logMessage('[DEBUG]', "$qtyDecks decks for user '$userEmail'");
        $decksProcessed = 0;
        while ($deckrow = $stmt->fetch_assoc()) :
            $deckNumber = $deckrow['decknumber'];
            if ($decksProcessed === 0) :
                $decksProcessed = $decksProcessed + 1;
                $msg->logMessage('[DEBUG]', "Processing deck $decksProcessed/$qtyDecks");
                $deckZipPath = $decks->exportDeck($deckNumber, "bulk");
                if ($deckZipPath === false) :
                    $msg->logMessage('[ERROR]', "Error returned from deckManager");
                    exit;
                endif;
            else :
                $decksProcessed = $decksProcessed + 1;
                $msg->logMessage('[DEBUG]', "Processing deck $decksProcessed/$qtyDecks");
                $addnext = $decks->exportDeck($deckNumber, "bulk", $deckZipPath);
                if ($addnext === false) :
                    $msg->logMessage('[ERROR]', "Error returned from deckManager");
                    exit;
                endif;
            endif;
        endwhile;
    endif;

    // Collection
    $attachments = [];
    $cleanupFiles = [];

    $msg->logMessage('[DEBUG]', "Preparing weekly collection export for $userEmail");
    $collectionCsv = $obj->buildCollectionCsv($usertable);
    if ($collectionCsv === false) :
        $msg->logMessage('[ERROR]', "Weekly collection export failed for $userEmail");
        $collectionTempFile = '';
    else :
        $collectionTempFile = tempnam(sys_get_temp_dir(), 'export_');
        file_put_contents($collectionTempFile, $collectionCsv);
        $cleanupFiles[] = $collectionTempFile;
    endif;

    $msg->logMessage('[DEBUG]', "Preparing weekly value history attachment for $userEmail");
    $historyData = $historyExporter->getHistoryData($userNumber, 'all');
    if ($historyData === false) :
        $msg->logMessage('[ERROR]', "Weekly value history export failed for $userEmail");
    else :
        $historyCsv = $historyExporter->buildCsv($historyData);
        if ($historyCsv === '') :
            $msg->logMessage('[ERROR]', "Weekly value history CSV build failed for $userEmail");
        else :
            $historyTempFile = tempnam(sys_get_temp_dir(), 'history_');
            file_put_contents($historyTempFile, $historyCsv);
            $attachments[] = ['path' => $historyTempFile, 'name' => 'value_history.csv'];
            $cleanupFiles[] = $historyTempFile;
            $msg->logMessage('[DEBUG]', "Weekly value history attachment ready for $userEmail");
        endif;
    endif;

    if ($deckZipPath !== '') :
        $attachments[] = ['path' => $deckZipPath, 'name' => basename($deckZipPath)];
        $cleanupFiles[] = $deckZipPath;
    endif;

    $subject = "$siteTitleEsc weekly export";
    $emailbody = "Hi $userName, please see attached your weekly export from $siteTitleEsc. <br><br> Opt out "
        . "of automated emails in your profile at <a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
    $emailaltbody = "Hi $userName, please see attached your weekly export from $siteTitleEsc. \r\n\r\n Opt out "
        . "of automated emails in your profile at your $siteTitleEsc profile page ($myURL/profile.php) \r\n\r\n";

    if (isset($emailEnabled) && $emailEnabled === true) :
        if ($collectionTempFile !== '') :
            $mail = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
            $mailresult = $mail->sendEmail(
                $userEmail,
                true,
                $subject,
                $emailbody,
                $emailaltbody,
                $collectionTempFile,
                'export.csv',
                $attachments
            );
        else :
            $msg->logMessage('[ERROR]', "Weekly export not sent to $userEmail (missing collection export)");
            $mailresult = false;
        endif;
    else :
        $msg->logMessage(
            '[NOTICE]',
            "Email disabled; weekly export not sent to $userEmail"
        );
        $mailresult = false;
    endif;

    foreach ($cleanupFiles as $cleanupFile) :
        if (is_string($cleanupFile) && $cleanupFile !== '' && file_exists($cleanupFile)) :
            unlink($cleanupFile);
        endif;
    endforeach;
    $list .= "$userName ($userEmail)\r\n";
endwhile;

$subject = "$siteTitleEsc weekly export user report";
$emailbody = "Weekly collection export from $siteTitleEsc have been run for:\r\n\r\n$list";
if (isset($emailEnabled) && $emailEnabled === true) :
    $mail = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
    $mailresult = $mail->sendEmail($adminEmail, false, $subject, $emailbody);
else :
    $msg->logMessage('[NOTICE]', 'Email disabled; weekly export admin summary not sent');
    $mailresult = false;
endif;
if (php_sapi_name() == 'cli') :
    echo "Weekly collection export from $siteTitleEsc have been run for:\n$list\n";
endif;
