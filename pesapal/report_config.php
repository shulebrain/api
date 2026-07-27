<?php

declare(strict_types=1);

// Ensure required environment variables exist
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
$sharedSecret = getenv('REPORT_RELAY_SECRET');

if (!$supabaseUrl || !$supabaseKey || !$sharedSecret) {
    error_log('[Relay][report_config] Missing critical environment variables on Render.');
}

return [
    'supabase_url'         => $supabaseUrl ?: '',
    'supabase_service_key' => $supabaseKey ?: '',
    'shared_secret'        => $sharedSecret ?: '',

    'max_request_age_seconds' => 300,

    // Timeouts for the outbound call to Supabase
    'connect_timeout' => 5,
    'request_timeout' => 10,
];