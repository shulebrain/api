<?php

    declare(strict_types=1);

    /**
     * ============================================================
     * relay/report_school.php
     * ============================================================
     * Called by PesapalService::reportSchoolToSupabase() right after every
     * successful (COMPLETED) payment. Holds the Supabase service_role key
     * server-side — this is the only place that key exists; it is never
     * shipped inside the desktop app.
     *
     * Verifies an HMAC signature over the request body (shared secret,
     * same pattern as relay/check_status.php) so an arbitrary caller can't
     * spam fake school records into Supabase.
     *
     * Never trusts which centre_number/exam_class_code "should" be
     * associated with which category — that decision is made entirely on
     * the desktop side (PesapalService already knows, from its own local
     * database, whether a payment was FACILITY or EXAM). This endpoint's
     * job is purely to relay an already-decided, already-signed report
     * through to Supabase using a credential the desktop app never has
     * direct access to.
     */

    header('Content-Type: application/json');

    function reportRespond(array $body, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($body);
        exit;
    }

    try {
        try {
            $configFile = __DIR__ . '/report_config.php';
            if (!is_file($configFile)) {
                throw new RuntimeException('relay/report_config.php not found.');
            }
            $config = require $configFile;
        } catch (Throwable $e) {
            error_log('[Relay][report_school] Config load failed: ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => 'Relay misconfigured.'], 500);
        }

        // ── Read and parse the signed request body ────────────────────────
        $rawInput = '';
        $data     = null;
        try {
            $rawInput = (string)file_get_contents('php://input');
            $data     = $rawInput !== '' ? json_decode($rawInput, true) : null;
            if (!is_array($data)) {
                throw new RuntimeException('Request body was not valid JSON.');
            }
        } catch (Throwable $e) {
            error_log('[Relay][report_school] Body parse failed: ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => 'Malformed request body.'], 400);
        }

        // ── Signature + freshness verification ────────────────────────────
        try {
            $ts  = (string)($data['ts'] ?? '');
            $sig = (string)($data['sig'] ?? '');
            $payload = $data['payload'] ?? null;

            if ($ts === '' || $sig === '' || !is_array($payload)) {
                throw new InvalidArgumentException('Missing ts, sig, or payload.');
            }
            if (!ctype_digit($ts)) {
                throw new InvalidArgumentException('ts must be a unix timestamp.');
            }

            $age = abs(time() - (int)$ts);
            if ($age > (int)($config['max_request_age_seconds'] ?? 300)) {
                throw new RuntimeException('Request timestamp outside allowed window (possible replay).');
            }

            $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES) . '|' . $ts;
            $expected  = hash_hmac('sha256', $canonical, (string)$config['shared_secret']);

            if (!hash_equals($expected, $sig)) {
                throw new RuntimeException('Signature mismatch.');
            }
        } catch (InvalidArgumentException $e) {
            error_log('[Relay][report_school] Rejected: ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => 'Bad request.'], 400);
        } catch (Throwable $e) {
            error_log('[Relay][report_school] Signature/freshness check failed: ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => 'Invalid or expired signature.'], 401);
        }

        // ── Validate the payload shape before forwarding to Supabase ─────
        try {
            $required = ['centre_number', 'centre_name', 'region', 'district', 'category'];
            foreach ($required as $field) {
                if (empty($payload[$field])) {
                    throw new InvalidArgumentException('Missing required field: ' . $field);
                }
            }
            if (!in_array($payload['category'], ['FACILITY', 'EXAM'], true)) {
                throw new InvalidArgumentException('category must be FACILITY or EXAM.');
            }
        } catch (InvalidArgumentException $e) {
            error_log('[Relay][report_school] Payload validation failed: ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        // ── Upsert into Supabase via PostgREST ────────────────────────────
        try {
            $row = [
                'centre_number'           => (string)$payload['centre_number'],
                'centre_name'             => (string)$payload['centre_name'],
                'region'                  => (string)$payload['region'],
                'district'                => (string)$payload['district'],
                'phone_number'            => $payload['phone_number'] ?? null,
                'email_address'           => $payload['email_address'] ?? null,
                'category'                => (string)$payload['category'],
                'last_payment_ref'        => $payload['last_payment_ref'] ?? null,
                'last_successful_payment' => $payload['last_successful_payment'] ?? null,
            ];

            $url = rtrim($config['supabase_url'], '/') . '/rest/v1/schools?on_conflict=centre_number';

            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('curl_init failed for Supabase upsert.');
            }

            try {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([$row], JSON_UNESCAPED_SLASHES),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'apikey: ' . $config['supabase_service_key'],
                        'Authorization: Bearer ' . $config['supabase_service_key'],
                        'Prefer: resolution=merge-duplicates,return=minimal',
                    ],
                    CURLOPT_CONNECTTIMEOUT => (int)($config['connect_timeout'] ?? 5),
                    CURLOPT_TIMEOUT        => (int)($config['request_timeout'] ?? 10),
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);

                $body     = curl_exec($ch);
                $errNo    = curl_errno($ch);
                $errMsg   = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($errNo !== 0) {
                    throw new RuntimeException("cURL transport error ({$errNo}): {$errMsg}");
                }
                if ($httpCode >= 300) {
                    throw new RuntimeException('Supabase upsert failed, HTTP ' . $httpCode . ': ' . substr((string)$body, 0, 500));
                }
            } finally {
                curl_close($ch);
            }

        } catch (Throwable $e) {
            error_log('[Relay][report_school] Supabase upsert failed for ' . ($payload['centre_number'] ?? '?') . ': ' . $e->getMessage());
            reportRespond(['ok' => false, 'message' => 'Could not record this payment right now.'], 502);
        }

        reportRespond(['ok' => true, 'message' => 'School record updated.']);

    } catch (Throwable $e) {
        error_log('[Relay][report_school] Uncaught top-level failure: ' . $e->getMessage());
        reportRespond(['ok' => false, 'message' => 'Unexpected relay failure.'], 500);
    }
?>