<?php

    declare(strict_types=1);

    /**
     * ============================================================
     * relay/pesapal_ipn.php
     * ============================================================
     * Deploy this on a small, always-on, PUBLIC HTTPS host (e.g.
     * pay.shulebrain.co.tz) — this is the ONE thing in the whole Pesapal
     * integration that must be a real public server, because Pesapal's
     * RegisterIPNURL requires a reachable notification_id endpoint before
     * any SubmitOrderRequest can be made.
     *
     * IMPORTANT: unlike the AzamPay relay this system replaced, this file
     * does NOT need to be a source of truth. PesapalService confirms every
     * payment by calling GetTransactionStatus directly, using the
     * order_tracking_id returned synchronously from SubmitOrderRequest —
     * long before any IPN ever arrives. So this endpoint only needs to:
     *   1. Acknowledge Pesapal's ping so RegisterIPNURL succeeds.
     *   2. Optionally log what it received, purely for your own debugging
     *      /audit trail — nothing downstream depends on this log.
     *
     * Pesapal will call this as either GET or POST (whichever
     * ipn_notification_type you registered — config/pesapal.php uses GET).
     * The expected response echoes back the three identifying fields.
     */

    try {
        try {
            $type      = (string)($_GET['pesapal_notification_type'] ?? $_POST['pesapal_notification_type'] ?? '');
            $trackingId = (string)($_GET['pesapal_transaction_tracking_id'] ?? $_POST['pesapal_transaction_tracking_id'] ?? '');
            $merchantRef = (string)($_GET['pesapal_merchant_reference'] ?? $_POST['pesapal_merchant_reference'] ?? '');
        } catch (Throwable $e) {
            $type = $trackingId = $merchantRef = '';
        }

        try {
            $logDir = __DIR__ . '/../storage';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0700, true);
            }
            @file_put_contents(
                $logDir . '/pesapal_ipn.log',
                '[' . date('Y-m-d H:i:s') . "] type={$type} tracking_id={$trackingId} merchant_ref={$merchantRef}" . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable $e) {
            // Logging failure is not fatal — Pesapal still needs its ack below.
            error_log('[Relay][pesapal_ipn] Logging failed: ' . $e->getMessage());
        }

        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'pesapal_notification_type'        => $type,
            'pesapal_transaction_tracking_id'   => $trackingId,
            'pesapal_merchant_reference'        => $merchantRef,
            'status'                            => 200,
        ]);

    } catch (Throwable $e) {
        error_log('[Relay][pesapal_ipn] Uncaught failure: ' . $e->getMessage());
        // Still answer 200 — Pesapal's job here is just to have pinged us.
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json');
        }
        echo '{"status":200}';
    }
?>
