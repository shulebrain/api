<?php
// Azampay IPN Handler
$log_file = '../webhook.log';
$timestamp = date('Y-m-d H:i:s');

$log = "\n=== $timestamp - AZAMPAY IPN ===\n";
$log .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "POST Data: " . print_r($_POST, true) . "\n";
$log .= "Raw Input: " . file_get_contents('php://input') . "\n";

file_put_contents($log_file, $log, FILE_APPEND);

http_response_code(200);
echo "Azampay IPN Received - OK";
?>
