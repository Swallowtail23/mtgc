<?php

/*
Version:     1.1
Date:        08/12/25
Name:        ajaxcollectionhistory.php
Purpose:     Return collection value history for charting.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 08/12/25 Initial version
*/

if (file_exists('../includes/sessionname.local.php')) :
    require '../includes/sessionname.local.php';
else :
    require '../includes/sessionname_template.php';
endif;
startCustomSession();
require '../includes/ini.php';
require '../includes/error_handling.php';
require '../includes/functions.php';

$msg = new Message($logfile);
$msg->logMessage('[DEBUG]', 'ajaxcollectionhistory.php: start');

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionhistory.php: not authenticated');
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
endif;

$userId = (int) $_SESSION['user'];

$range = isset($_GET['range']) ? strtolower(trim($_GET['range'])) : '30d';
$validRanges = ['30d', '90d', '1y', 'all'];
if (!in_array($range, $validRanges, true)) :
    $range = '30d';
endif;
$msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: user {$userId}, range {$range}");

$where = "usernumber = ?";
$params = [$userId];
$types = "i";
if ($range === '30d') :
    $where .= " AND collected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
elseif ($range === '90d') :
    $where .= " AND collected_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
elseif ($range === '1y') :
    $where .= " AND collected_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
endif;

$query = "
    SELECT collected_at, value_usd, value_local, rate_used, card_count
    FROM collection_values
    WHERE $where
    ORDER BY collected_at ASC
";

$stmt = $db->prepare($query);
if ($stmt === false) :
    $msg->logMessage('[ERROR]', "ajaxcollectionhistory.php prepare failed: " . $db->error);
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    exit();
endif;

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) :
    $msg->logMessage('[ERROR]', "ajaxcollectionhistory.php execute failed: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    $stmt->close();
    exit();
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
$msg->logMessage('[DEBUG]', "ajaxcollectionhistory.php: returned " . count($data) . " rows");

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $data]);
exit();
