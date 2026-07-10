<?php
// Main Router for Shulebrain Payment Webhooks

$path = strtolower($_SERVER['REQUEST_URI']);

if (strpos($path, '/pesapal') !== false || strpos($path, 'pesapal') !== false) {
    require_once 'pesapal/callback.php';
} 
elseif (strpos($path, '/azampay') !== false || strpos($path, 'azampay') !== false) {
    require_once 'azampay/ipn.php';
} 
else {
    // Default homepage
    http_response_code(200);
    echo "Shulebrain Payment Callback Service is Running";
}
?>
