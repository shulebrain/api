<?php
// Simple Webhook Receiver for Pesapal and Azampay

// Log all requests for debugging
$log = date('Y-m-d H:i:s') . " - Webhook received\n";
$log .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "POST Data: " . print_r($_POST, true) . "\n";
$log .= "Raw Input: " . file_get_contents('php://input') . "\n\n";

file_put_contents('webhook.log', $log, FILE_APPEND);

// Always respond quickly with OK
http_response_code(200);
echo "OK";

// You can add your payment processing logic here later
?>
