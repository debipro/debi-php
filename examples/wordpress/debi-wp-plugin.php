<?php

/**
 * Plugin Name: Debi (example integration)
 * Description: Minimal reference WordPress plugin showing how to wire the
 *              Debi PHP SDK to wp_remote_request and how to receive signed
 *              webhooks via the REST API.
 * Version:     0.1.0
 * Requires PHP: 8.1
 * License:     MIT
 *
 * This is illustrative code, not a production-ready plugin. It exists to
 * show, in one file, the three things every WordPress integrator gets wrong:
 *
 *   1. How to avoid Guzzle version conflicts with other plugins.
 *   2. How to read the raw webhook body without WP_REST mutating it.
 *   3. How to bootstrap a DebiClient lazily (one per request, not per call).
 *
 * Expected directory layout when adapting to your own plugin:
 *
 *   my-plugin/
 *     my-plugin.php          ← this file, renamed
 *     src/WpHttpClient.php   ← copy of examples/wordpress/WpHttpClient.php
 *     vendor/                ← composer install (debi/debi-php + nyholm/psr7)
 */

declare(strict_types=1);

namespace Debi\Examples\WordPress\Plugin;

use Debi\DebiClient;
use Debi\Examples\WordPress\WpHttpClient;
use Debi\Exception\ApiErrorException;
use Debi\Exception\ExceptionInterface;
use Debi\Exception\SignatureVerificationException;
use Debi\HttpClient\DefaultClient;
use Debi\Webhook;
use Nyholm\Psr7\Factory\Psr17Factory;

if (!defined('ABSPATH')) {
    // Direct access guard. Standard WordPress hygiene.
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WpHttpClient.php';

/**
 * Lazily build a single DebiClient per request. The instance is cached in a
 * static so repeated calls inside the same request reuse one HTTP transport
 * and one PSR-17 factory — important because PSR-18 discovery is *not*
 * cheap, and we want the SDK's retry/idempotency layer to see consistent
 * state across calls.
 */
function debi_client(): DebiClient
{
    static $client = null;
    if ($client instanceof DebiClient) {
        return $client;
    }

    $apiKey = (string) get_option('debi_api_key', '');
    if ($apiKey === '') {
        throw new \RuntimeException(
            'Debi API key is not configured. Set the `debi_api_key` option.'
        );
    }

    $psr17 = new Psr17Factory();
    $http  = new WpHttpClient(
        responseFactory: $psr17,
        streamFactory:   $psr17,
        timeout:         (int) apply_filters('debi_http_timeout', 30),
    );

    return $client = new DebiClient([
        'api_key'  => $apiKey,
        // Switch to DebiClient::DEFAULT_SANDBOX_BASE when DEBI_SANDBOX is on.
        'api_base' => defined('DEBI_SANDBOX') && DEBI_SANDBOX
            ? DebiClient::DEFAULT_SANDBOX_BASE
            : DebiClient::DEFAULT_API_BASE,
        // Wrapping our PSR-18 client in DefaultClient keeps the SDK's
        // exponential-backoff + idempotent-retry policy intact; we only
        // replace the transport, not the retry layer.
        'http_client' => new DefaultClient([
            'http_client'     => $http,
            'request_factory' => $psr17,
            'stream_factory'  => $psr17,
        ]),
    ]);
}

// ---------------------------------------------------------------------------
// Example 1: create a Debi customer when a WordPress user registers.
// ---------------------------------------------------------------------------

add_action('user_register', function (int $userId): void {
    $user = get_userdata($userId);
    if (!$user) {
        return;
    }

    try {
        $customer = debi_client()->customers->create(
            [
                'email' => $user->user_email,
                'name'  => trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name,
            ],
            // Tying the idempotency key to the WP user id makes a retried
            // registration (e.g. duplicate hook fire on a flaky network)
            // safe: the Debi API will return the original customer, not a
            // duplicate record.
            ['idempotency_key' => 'wp-user-' . $userId],
        );

        update_user_meta($userId, 'debi_customer_id', $customer->id);
    } catch (ApiErrorException $e) {
        // Don't block user registration on a downstream Debi failure.
        // Log it so an operator can backfill the customer later.
        error_log(sprintf(
            '[debi] failed to create customer for user %d: status=%d msg=%s',
            $userId,
            $e->httpStatus,
            $e->getMessage(),
        ));
    } catch (ExceptionInterface $e) {
        error_log(sprintf(
            '[debi] failed to create customer for user %d: %s',
            $userId,
            $e->getMessage(),
        ));
    }
});

// ---------------------------------------------------------------------------
// Example 2: receive signed webhooks via the REST API.
//
// Endpoint: POST /wp-json/debi/v1/webhook
// ---------------------------------------------------------------------------

add_action('rest_api_init', function (): void {
    register_rest_route('debi/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\handle_webhook',
        // The HMAC signature *is* the authentication. No nonce, no cookie
        // check, no capability check — Debi's servers cannot present any of
        // those. Returning anything but true here would 403 every delivery.
        'permission_callback' => '__return_true',
    ]);
});

function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
{
    // CRITICAL: read the raw bytes, never the parsed JSON. `get_body()`
    // returns the unmodified request body string. Re-encoding via
    // `wp_json_encode($request->get_json_params())` would produce a
    // semantically equivalent but byte-different payload, and the HMAC
    // would no longer match.
    $rawBody = $request->get_body();

    // WordPress normalizes header names to lowercase-with-underscores when
    // exposed through WP_REST_Request, so `Debi-Signature` is reached as
    // `debi_signature`. Both forms work; we use the WP-normalized one.
    $signature = $request->get_header('debi_signature') ?? '';

    $secret = (string) get_option('debi_webhook_secret', '');
    if ($secret === '') {
        return new \WP_REST_Response(['error' => 'webhook secret not configured'], 500);
    }

    try {
        $event = Webhook::constructEvent($rawBody, $signature, $secret);
    } catch (SignatureVerificationException $e) {
        // Reply 400 (or 401) so Debi marks the delivery as failed and
        // retries with backoff. Do NOT echo the exception message into the
        // response body — it can leak which check failed (timestamp vs
        // signature vs JSON parse) and help an attacker iterate.
        error_log('[debi] webhook rejected: ' . $e->getMessage());
        return new \WP_REST_Response(['error' => 'invalid signature'], 400);
    }

    // Acknowledge quickly. Heavy work belongs on a queued action so Debi's
    // delivery timeout (a few seconds) isn't blocked by your processing.
    do_action('debi_event_received', $event);
    wp_schedule_single_event(time(), 'debi_process_event', [$event->id]);

    return new \WP_REST_Response(['received' => true], 200);
}

// Async worker: do the actual business logic here. Runs on a separate
// request via WP-Cron, so this function must re-fetch any state it needs.
add_action('debi_process_event', function (string $eventId): void {
    try {
        $event = debi_client()->events->retrieve($eventId);
    } catch (ExceptionInterface $e) {
        error_log('[debi] could not re-fetch event ' . $eventId . ': ' . $e->getMessage());
        return;
    }

    switch ($event->type) {
        case 'payment.succeeded':
            // mark order as paid, send receipt, etc.
            break;
        case 'subscription.canceled':
            // revoke access, etc.
            break;
        default:
            // Unknown event types are expected as Debi adds new ones.
            // Treat them as no-ops; do not 4xx them — that would cause
            // pointless retries.
            break;
    }
});
