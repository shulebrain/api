<?php
// Set header to display plain text cleanly in the browser
header('Content-Type: text/plain');

$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
$sharedSecret = getenv('REPORT_RELAY_SECRET');

echo "URL : " . $supabaseUrl . "<br />";
echo "KEY : " . $supabaseKey . "<br />";
echo "SEC : " . $sharedSecret . "<br />";
