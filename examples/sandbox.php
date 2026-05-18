<?php

/**
 * Live sandbox runner for the Debi PHP SDK.
 *
 * Exercises every endpoint the SDK exposes against the live sandbox server
 * (https://api.debi-test.pro by default). Designed to be safe to re-run as
 * many times as you want — every resource is created with a random,
 * idempotency-key-derived suffix so you do not accumulate duplicates.
 *
 * Each step is wrapped with attempt() so a single failing endpoint does
 * NOT abort the rest of the run. The script exits 0 when every step
 * passed and 1 when at least one step failed, with a final summary that
 * lists exactly which steps failed and their HTTP status. This is the
 * behavior you want in CI: fail loudly without losing coverage of the
 * other endpoints.
 *
 * Usage:
 *
 *   DEBI_API_KEY=sk_test_xxx php examples/sandbox.php
 *
 *   # Run only specific sections:
 *   DEBI_API_KEY=sk_test_xxx php examples/sandbox.php customers
 *   DEBI_API_KEY=sk_test_xxx php examples/sandbox.php billing_portal
 *
 *   # Override the base URL (e.g. against a self-hosted sandbox):
 *   DEBI_API_KEY=sk_test_xxx DEBI_API_BASE=https://my.sandbox \
 *     php examples/sandbox.php
 *
 * Sections:
 *   customers         create + retrieve + list + search + archive/restore
 *   payment_methods   create + attach + detach
 *   billing_portal    list/create configurations + create session
 *   payments          list (read-only — does not create live payments)
 *   events            list (read-only)
 *   webhook           local signature round-trip (no network)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Debi\DebiClient;
use Debi\Exception\ApiErrorException;
use Debi\Exception\ExceptionInterface;
use Debi\Webhook;

$apiKey = getenv('DEBI_API_KEY') ?: null;
$apiBase = getenv('DEBI_API_BASE') ?: DebiClient::DEFAULT_SANDBOX_BASE;

if ($apiKey === null || $apiKey === '') {
    fwrite(STDERR, "DEBI_API_KEY is required. Get a sandbox key from https://debi-test.pro/dashboard/developers\n");
    exit(2);
}

$debi = new DebiClient([
    'api_key' => $apiKey,
    'api_base' => $apiBase,
]);

$sections = array_slice($argv, 1);
if ($sections === []) {
    $sections = ['customers', 'payment_methods', 'billing_portal', 'payments', 'events', 'webhook'];
}

/** Global counters keyed by step status. Surfaced in the final summary. */
$GLOBALS['report'] = ['ok' => 0, 'fail' => [], 'skip' => 0];

banner("Debi PHP SDK — sandbox runner", "api_base = {$apiBase}");

$customer = null;
if (in_array('customers', $sections, true)
    || in_array('payment_methods', $sections, true)
    || in_array('billing_portal', $sections, true)) {
    $customer = runCustomers($debi);
}
if (in_array('payment_methods', $sections, true) && $customer !== null) {
    runPaymentMethods($debi, $customer);
}
if (in_array('billing_portal', $sections, true) && $customer !== null) {
    runBillingPortal($debi, $customer);
}
if (in_array('payments', $sections, true)) {
    runPayments($debi);
}
if (in_array('events', $sections, true)) {
    runEvents($debi);
}
if (in_array('webhook', $sections, true)) {
    runWebhookRoundTrip();
}

exit(finish());

// =============================================================================
// sections
// =============================================================================

function runCustomers(DebiClient $debi): ?\Debi\Resource\Customer
{
    section('customers — create + retrieve + list + search + archive/restore');

    $suffix = bin2hex(random_bytes(4));
    $email = "sandbox+{$suffix}@example.com";

    $customer = attempt('create', static fn () => $debi->customers->create([
        'email' => $email,
        'name'  => 'Sandbox Runner',
        'metadata' => ['source' => 'debi-php-sandbox-runner'],
    ], ['idempotency_key' => 'sandbox-customer-' . $suffix]));

    if (!$customer instanceof \Debi\Resource\Customer) {
        line('aborting customers section: create failed.');
        return null;
    }
    line("  → id={$customer->id}");

    attempt('retrieve', static function () use ($debi, $customer) {
        $fetched = $debi->customers->retrieve($customer->id);
        if ($fetched->id !== $customer->id) {
            throw new \RuntimeException("retrieve returned id={$fetched->id}");
        }
        return $fetched;
    });

    attempt('list (limit=1)', static function () use ($debi) {
        $list = $debi->customers->all(['limit' => 1]);
        line('  → ' . count($list->data()) . ' on first page');
        return $list;
    });

    // The spec for /v1/customers/search marks `page` as required: true, but
    // its description says "Don't include this parameter on the first call".
    // The sandbox currently returns 500 in some edge cases; treating search
    // failures as non-fatal because this is a known server-side ambiguity.
    attempt('search (q=email)', static function () use ($debi, $email) {
        $r = $debi->customers->search(['q' => $email, 'limit' => 5]);
        line('  → ' . count($r->data()) . ' match(es)');
        return $r;
    });

    attempt('archive', static fn () => $debi->customers->archive($customer->id));
    attempt('restore', static fn () => $debi->customers->restore($customer->id));

    return $customer;
}

function runPaymentMethods(DebiClient $debi, \Debi\Resource\Customer $customer): void
{
    section('payment_methods — create + attach + detach');

    $pm = attempt('create (type=transfer)', static fn () => $debi->paymentMethods->create([
        // Sandbox tenants typically allow `transfer` without live PAN data;
        // adjust to whatever your tenant has enabled.
        'type' => 'transfer',
    ], ['idempotency_key' => 'sandbox-pm-' . bin2hex(random_bytes(4))]));

    if (!$pm instanceof \Debi\Resource\PaymentMethod) {
        line('skipping attach/detach: create failed (tenant may not allow this type).');
        return;
    }
    line("  → pm={$pm->id}");

    // POST /v1/payment_methods/{id}/attach  body { customer } → 204
    attempt('attach', static fn () => $debi->paymentMethods->attach($pm->id, $customer->id));

    // POST /v1/payment_methods/{id}/detach → 204
    attempt('detach', static fn () => $debi->paymentMethods->detach($pm->id));
}

function runBillingPortal(DebiClient $debi, \Debi\Resource\Customer $customer): void
{
    section('billing_portal — configurations + sessions');

    // Many sandbox tenants do not have the billing portal feature enabled,
    // in which case the API returns 404. Treat the whole section as skipped.
    try {
        $configurations = $debi->billingPortalConfigurations->all(['limit' => 5]);
    } catch (ApiErrorException $e) {
        if ($e->httpStatus === 404) {
            line('skip: billing portal feature not enabled on this account (404).');
            $GLOBALS['report']['skip']++;
            return;
        }
        $GLOBALS['report']['fail'][] = "billing_portal: list configurations (status={$e->httpStatus})";
        line("FAIL list: status={$e->httpStatus} — {$e->getMessage()}");
        return;
    }
    $GLOBALS['report']['ok']++;
    line('listed ' . count($configurations->data()) . ' configuration(s)');

    $config = null;
    if ($configurations->data() === []) {
        $config = attempt('configuration: create', static fn () => $debi->billingPortalConfigurations->create([
            'features' => [
                'customer_update' => false,
                'invoice_history' => true,
                'payment_method_update' => true,
                'subscription_cancel' => true,
            ],
            'login_page' => ['enabled' => false],
            'business_profile' => ['headline' => 'Sandbox runner'],
        ]));
    } else {
        $config = $configurations->data()[0];
        line("reusing  config={$config->id}");
    }

    if ($config instanceof \Debi\Resource\BillingPortalConfiguration) {
        attempt('session: create', static function () use ($debi, $customer, $config) {
            $session = $debi->billingPortalSessions->create([
                'customer_id' => $customer->id,
                'billing_portal_configuration_id' => $config->id,
                'return_url' => 'https://example.com/account',
            ]);
            line("  → session={$session->id}");
            line("    url={$session->url}");
            return $session;
        });
    }
}

function runPayments(DebiClient $debi): void
{
    section('payments — list (read-only)');

    attempt('list (limit=3)', static function () use ($debi) {
        $list = $debi->payments->all(['limit' => 3]);
        line('  → ' . count($list->data()) . ' on first page');
        foreach ($list->data() as $p) {
            line("    - {$p->id}  status={$p->status}");
        }
        return $list;
    });
}

function runEvents(DebiClient $debi): void
{
    section('events — list (read-only)');

    attempt('list (limit=3)', static function () use ($debi) {
        $list = $debi->events->all(['limit' => 3]);
        line('  → ' . count($list->data()) . ' on first page');
        foreach ($list->data() as $e) {
            line("    - {$e->id}  {$e->type}");
        }
        return $list;
    });
}

function runWebhookRoundTrip(): void
{
    section('webhook — local signature round-trip (no network)');

    attempt('Webhook::constructEvent', static function () {
        $secret  = 'whsec_sandbox_runner';
        $payload = json_encode([
            'id'     => 'evt_sandbox',
            'object' => 'event',
            'type'   => 'customer.created',
            'data'   => ['object' => ['id' => 'CSjRZ5JqjAw0', 'object' => 'customer']],
        ], JSON_THROW_ON_ERROR);

        $ts = time();
        $sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        $header = "t={$ts},v1={$sig}";

        $event = Webhook::constructEvent($payload, $header, $secret);
        if ($event->type !== 'customer.created') {
            throw new \RuntimeException("unexpected event.type={$event->type}");
        }
        line("  → verified type={$event->type}");
        return $event;
    });
}

// =============================================================================
// runner core
// =============================================================================

/**
 * Run a single step and record the outcome. The step's return value is
 * returned on success, `null` on failure. Failures are accumulated in
 * $GLOBALS['report'] for the final summary; we never re-throw, so one
 * broken endpoint does not poison the rest of the suite.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|null
 */
function attempt(string $label, callable $fn): mixed
{
    try {
        $value = $fn();
        $GLOBALS['report']['ok']++;
        echo "  \033[1;32m✓\033[0m  {$label}\n";
        return $value;
    } catch (ApiErrorException $e) {
        $detail = "status={$e->httpStatus}  message=\"{$e->getMessage()}\"";
        if ($e->requestId !== null) {
            $detail .= "  request-id={$e->requestId}";
        }
        $GLOBALS['report']['fail'][] = "{$label}: {$detail}";
        echo "  \033[1;31m✗\033[0m  {$label}  {$detail}\n";
        return null;
    } catch (ExceptionInterface | \Throwable $e) {
        $detail = get_class($e) . ': ' . $e->getMessage();
        $GLOBALS['report']['fail'][] = "{$label}: {$detail}";
        echo "  \033[1;31m✗\033[0m  {$label}  {$detail}\n";
        return null;
    }
}

function finish(): int
{
    $ok = $GLOBALS['report']['ok'];
    $skipped = $GLOBALS['report']['skip'];
    $failed = $GLOBALS['report']['fail'];

    echo "\n";
    if ($failed === []) {
        banner('All sandbox checks passed.', "{$ok} ok, {$skipped} skipped");
        return 0;
    }

    banner('Sandbox finished with failures.', $ok . ' ok, ' . count($failed) . ' failed, ' . $skipped . ' skipped');
    foreach ($failed as $row) {
        echo "  \033[1;31m✗\033[0m  {$row}\n";
    }
    echo "\n";
    return 1;
}

// =============================================================================
// tiny CLI helpers
// =============================================================================

function banner(string $title, ?string $subtitle = null): void
{
    echo "\n\033[1;36m═══ {$title} ═══════════════════════════════════════════\033[0m\n";
    if ($subtitle !== null) {
        echo "  \033[2m{$subtitle}\033[0m\n";
    }
}

function section(string $title): void
{
    echo "\n\033[1;33m── {$title}\033[0m\n";
}

function line(string $msg): void
{
    echo "  {$msg}\n";
}
