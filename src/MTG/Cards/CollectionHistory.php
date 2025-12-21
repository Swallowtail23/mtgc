<?php

/*
Version:     1.1
Date:        21/12/25
Name:        CollectionHistory.php
Purpose:     Collection value history retrieval and export helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

class CollectionHistory
{
    /**
    * @var mysqli
    */
    private $db;
    private $logfile;
    private $message;
    private $siteTitle;
    private $serverEmail;

    public function __construct($db, $logfile, $siteTitle = null, $serverEmail = null)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new \MTG\Core\Message($this->logfile);
        $this->siteTitle = $siteTitle ?: $GLOBALS['siteTitle'];
        $this->serverEmail = $serverEmail ?: $GLOBALS['serverEmail'];
    }

    public function getHistoryData($userId, $range)
    {
        $range = $this->normaliseRange($range);
        $this->message->logMessage(
            '[DEBUG]',
            "CollectionHistory: fetching history for user $userId, range $range"
        );

        $startDate = $this->rangeToStartDate($range);
        if ($startDate === null) :
            $this->message->logMessage('[DEBUG]', 'CollectionHistory: using full history range');
        else :
            $this->message->logMessage('[DEBUG]', "CollectionHistory: start date $startDate");
        endif;

        $query = "
            SELECT collected_at, value_usd, value_local, rate_used, card_count
            FROM collection_values
            WHERE usernumber = ?
            AND (? IS NULL OR collected_at >= ?)
            ORDER BY collected_at ASC
        ";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) :
            $this->message->logMessage(
                '[ERROR]',
                "CollectionHistory: prepare failed: " . $this->db->error
            );
            return false;
        endif;

        $stmt->bind_param('iss', $userId, $startDate, $startDate);
        if (!$stmt->execute()) :
            $this->message->logMessage(
                '[ERROR]',
                "CollectionHistory: execute failed: " . $stmt->error
            );
            $stmt->close();
            return false;
        endif;

        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) :
            $collectedAt = date('Y-m-d', strtotime($row['collected_at']));
            $data[] = [
                't' => $collectedAt,
                'usd' => (float) $row['value_usd'],
                'local' => ($row['value_local'] === null ? null : (float) $row['value_local']),
                'rate' => ($row['rate_used'] === null ? null : (float) $row['rate_used']),
                'cards' => (int) $row['card_count'],
            ];
        endwhile;
        $stmt->close();

        $this->message->logMessage(
            '[DEBUG]',
            "CollectionHistory: returned " . count($data) . " rows"
        );

        return $data;
    }

    public function buildCsv(array $data)
    {
        $this->message->logMessage(
            '[DEBUG]',
            "CollectionHistory: building CSV from " . count($data) . " rows"
        );

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) :
            $this->message->logMessage('[ERROR]', 'CollectionHistory: unable to open temp CSV buffer');
            return '';
        endif;

        fputcsv($handle, ['collected_at', 'value_usd', 'value_local', 'rate_used', 'card_count'], ',', '"', '\\');
        foreach ($data as $row) :
            fputcsv(
                $handle,
                [
                    $row['t'],
                    $row['usd'],
                    $row['local'],
                    $row['rate'],
                    $row['cards']
                ],
                ',',
                '"',
                '\\'
            );
        endforeach;

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function emailHistoryCsv(
        $userId,
        $myURL,
        $smtpParameters,
        $range,
        $filename,
        $userName,
        $userEmail
    ) {
        $this->message->logMessage('[DEBUG]', "CollectionHistory: preparing history export for $userEmail");

        $data = $this->getHistoryData($userId, $range);
        if ($data === false) :
            $this->message->logMessage('[ERROR]', "CollectionHistory: unable to fetch history for $userEmail");
            return false;
        endif;

        $csv = $this->buildCsv($data);
        if ($csv === '') :
            $this->message->logMessage('[ERROR]', "CollectionHistory: CSV build failed for $userEmail");
            return false;
        endif;

        if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
            $mail = new \MTG\Core\MyPHPMailer(
                true,
                $smtpParameters,
                $this->serverEmail,
                $this->logfile
            );

            $tempFile = tempnam(sys_get_temp_dir(), 'history_');
            file_put_contents($tempFile, $csv);

            $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
            $subject = "$this->siteTitle weekly value history export";
            $emailbody = "Hi $userName, your weekly value history export from $siteTitleEsc is attached."
                . "<br><br>Opt out of automated emails in your profile at "
                . "<a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
            $emailaltbody = "Hi $userName, please see attached your weekly value history export from "
                . "$this->siteTitle.\r\n\r\nOpt out of automated emails in your profile at your "
                . "$this->siteTitle profile page ($myURL/profile.php)\r\n\r\n";

            $mailresult = $mail->sendEmail(
                $userEmail,
                true,
                $subject,
                $emailbody,
                $emailaltbody,
                $tempFile,
                $filename
            );

            if (isset($tempFile)) :
                unlink($tempFile);
            endif;

            if ($mailresult === true) :
                $this->message->logMessage('[DEBUG]', "CollectionHistory: export sent to $userEmail");
                return true;
            endif;

            $this->message->logMessage('[ERROR]', "CollectionHistory: export failed for $userEmail");
            return $mailresult ?: false;
        else :
            $this->message->logMessage(
                '[NOTICE]',
                "Email disabled; value history export email not sent to $userEmail"
            );
            return false;
        endif;
    }

    private function normaliseRange($range)
    {
        $range = strtolower(trim((string) $range));
        $validRanges = ['30d', '90d', '1y', 'all'];
        if (!in_array($range, $validRanges, true)) :
            $this->message->logMessage(
                '[DEBUG]',
                "CollectionHistory: invalid range '$range', defaulting to 30d"
            );
            $range = '30d';
        endif;

        return $range;
    }

    private function rangeToStartDate($range)
    {
        if ($range === 'all') :
            return null;
        endif;

        $days = 30;
        if ($range === '90d') :
            $days = 90;
        elseif ($range === '1y') :
            $days = 365;
        endif;

        $start = new \DateTimeImmutable('now');
        $start = $start->modify("-$days days");

        return $start->format('Y-m-d H:i:s');
    }
}
