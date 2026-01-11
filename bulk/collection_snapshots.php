<?php

/*
Version:     1.14
Date:        11/01/26
Name:        collection_snapshots.php
Purpose:     Capture daily collection value snapshots for all active users.
Notes:       Uses collection_values table to store historical values.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CollectionStats;

require('bulk_ini.php');

$msg->logMessage('[NOTICE]', 'Starting collection value snapshot run');

$fxAPI = $iniArray['fx']['FreecurrencyAPI'] ?? '';
$fxLocal = $iniArray['fx']['TargetCurrency'] ?? '';
$adminip = isset($iniArray['security']['AdminIP']) ? $iniArray['security']['AdminIP'] : 1;
$statsHelper = new CollectionStats($db, $appConfig);

$todayStart = strtotime('today');
$users = $db->execute_query(
    "SELECT usernumber, username, email, status, currency FROM users WHERE status = 'active'"
);
if ($users === false) :
    throw new Exception('[ERROR] collection_snapshots.php: Failed to fetch users');
endif;

while ($user = $users->fetch_assoc()) :
    $userNumber = (int) $user['usernumber'];
    $userEmail = $user['email'];
    $userName = $user['username'];
    $userCurrency = trim((string) ($user['currency'] ?? ''));
    $tableName = $userNumber . "collection";

    // Ensure the collection table exists
    $tableCheck = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($tableName) . "'");
    if ($tableCheck === false || $tableCheck->num_rows === 0) :
        $msg->logMessage('[NOTICE]', "Skipping $userEmail - no collection table ($tableName)");
        continue;
    endif;

    // Rate limit: once per day
    $latest = $db->execute_query(
        "SELECT collected_at FROM collection_values WHERE usernumber = ? ORDER BY collected_at DESC LIMIT 1",
        [$userNumber]
    );
    if ($latest && $latest->num_rows === 1) :
        $row = $latest->fetch_assoc();
        if (strtotime($row['collected_at']) >= $todayStart) :
            $msg->logMessage('[DEBUG]', "Skipping $userEmail - snapshot already captured today");
            continue;
        endif;
    endif;

    $stats = $statsHelper->getStats($tableName, $userCurrency);
    $valueUsd = $stats['value_usd'];
    $localValue = $stats['value_local'];
    $localCurrency = $stats['local_currency'];
    $rateUsed = $stats['rate_used'];
    $totalCardCount = $stats['card_count'];
    $mrCardCount = $stats['mr_count'];

    $insert = $db->prepare(
        "INSERT INTO collection_values
        (usernumber, collected_at, value_usd, value_local, local_currency, rate_used, card_count, mr_count)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)"
    );
    if ($insert === false) :
        $msg->logMessage('[ERROR]', "Prepare failed for $userEmail: " . $db->error);
        continue;
    endif;
    $insert->bind_param(
        "idssdii",
        $userNumber,
        $valueUsd,
        $localValue,
        $localCurrency,
        $rateUsed,
        $totalCardCount,
        $mrCardCount
    );
    if (!$insert->execute()) :
        $msg->logMessage('[ERROR]', "Snapshot insert failed for $userEmail: " . $insert->error);
    else :
        $msg->logMessage(
            '[NOTICE]',
            "Snapshot stored for $userEmail: USD {$valueUsd}, local {$localValue} {$localCurrency}"
        );
        if (php_sapi_name() == 'cli') :
            echo "Snapshot stored for $userEmail: USD {$valueUsd}, local {$localValue} {$localCurrency}\n";
        endif;
    endif;
    $insert->close();
endwhile;

$msg->logMessage('[NOTICE]', 'Collection value snapshot run completed');

// If running from CLI, output result
if (php_sapi_name() == 'cli') :
    echo "Collection value snapshot run completed\n";
endif;
