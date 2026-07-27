<?php
// Set header to display plain text cleanly in the browser
header('Content-Type: text/plain');

echo "=== RENDER ENVIRONMENT VARIABLE TEST ===\n\n";

// 1. Fetch the variables
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseServiceKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

// 2. Display Supabase URL status
if ($supabaseUrl) {
    echo "✅ SUPABASE_URL found: " . $supabaseUrl . "\n";
} else {
    echo "❌ SUPABASE_URL is missing!\n";
}

// 3. Display Supabase Service Role Key status (masked for security)
if ($supabaseServiceKey) {
    // Show only the first 10 characters so you know it loaded without exposing full key in screen grabs
    $maskedKey = substr($supabaseServiceKey, 0, 10) . "********************";
    
    echo "✅ SUPABASE_SERVICE_ROLE_KEY found!\n";
    echo "   Key Preview: " . $maskedKey . "\n";
    echo "   Key Length:  " . strlen($supabaseServiceKey) . " characters\n";
} else {
    echo "❌ SUPABASE_SERVICE_ROLE_KEY is missing!\n";
}

echo "\n========================================";
