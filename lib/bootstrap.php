<?php
declare(strict_types=1);

const QRIS_SESSION_TOKEN_KEY = 'qris_api_token';
const QRIS_SESSION_ACCOUNT_KEY = 'qris_account';
const QRIS_SESSION_TRANSACTION_KEY = 'qris_transaction';
const QRIS_SESSION_CSRF_KEY = 'qris_csrf_token';

function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('qris_lab_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (!isset($_SESSION[QRIS_SESSION_CSRF_KEY])) {
        $_SESSION[QRIS_SESSION_CSRF_KEY] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    boot_session();
    return (string) $_SESSION[QRIS_SESSION_CSRF_KEY];
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        json_response([
            'success' => false,
            'message' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.',
        ], 419);
    }
}

function require_json_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_response(['success' => false, 'message' => 'Metode request tidak didukung.'], 405);
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_starts_with($contentType, 'application/json')) {
        json_response(['success' => false, 'message' => 'Request harus menggunakan JSON.'], 415);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 32_768) {
        json_response(['success' => false, 'message' => 'Payload request tidak valid.'], 400);
    }

    try {
        $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['success' => false, 'message' => 'JSON request tidak valid.'], 400);
    }

    if (!is_array($payload)) {
        json_response(['success' => false, 'message' => 'Payload request tidak valid.'], 400);
    }

    return $payload;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function session_api_token(): ?string
{
    boot_session();
    $token = $_SESSION[QRIS_SESSION_TOKEN_KEY] ?? null;
    return is_string($token) && $token !== '' ? $token : null;
}

function require_api_token(): string
{
    $token = session_api_token();
    if ($token === null) {
        json_response([
            'success' => false,
            'message' => 'Hubungkan API token terlebih dahulu.',
        ], 401);
    }
    return $token;
}

function mask_secret(string $value): string
{
    $length = strlen($value);
    if ($length <= 8) {
        return str_repeat('•', max(4, $length));
    }
    return substr($value, 0, 4) . str_repeat('•', min(12, $length - 8)) . substr($value, -4);
}

function scalar_string(mixed $value, string $fallback = ''): string
{
    return is_string($value) || is_numeric($value) ? (string) $value : $fallback;
}

function scalar_int(mixed $value, int $fallback = 0): int
{
    return is_int($value) || is_numeric($value) ? (int) $value : $fallback;
}

function scalar_bool(mixed $value, bool $fallback = false): bool
{
    return is_bool($value) ? $value : $fallback;
}

function public_account(array $response): array
{
    $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
    $providers = [];
    foreach (($data['providers'] ?? []) as $provider) {
        if (!is_array($provider)) {
            continue;
        }
        $providers[] = [
            'name' => scalar_string($provider['name'] ?? $provider['provider'] ?? ''),
            'configured' => scalar_bool($provider['configured'] ?? $provider['is_configured'] ?? false),
            'active' => scalar_bool($provider['active'] ?? $provider['is_active'] ?? false),
        ];
    }

    return [
        'username' => scalar_string($data['username'] ?? ''),
        'first_name' => scalar_string($data['first_name'] ?? ''),
        'active_provider' => scalar_string($data['active_provider'] ?? ''),
        'is_active' => scalar_bool($data['is_active'] ?? false),
        'token_valid' => scalar_bool($data['token_valid'] ?? true, true),
        'balance' => scalar_int($data['balance'] ?? 0),
        'qris_creation_fee' => scalar_int($data['qris_creation_fee'] ?? 0),
        'providers' => $providers,
    ];
}

function public_transaction(?array $transaction): ?array
{
    if ($transaction === null) {
        return null;
    }

    $reference = scalar_string($transaction['id'] ?? '');
    return [
        'status' => scalar_string($transaction['status'] ?? 'pending', 'pending'),
        'paid' => scalar_bool($transaction['paid'] ?? false),
        'qr_available' => $reference !== '',
        'reference' => $reference !== '' ? mask_secret($reference) : '',
        'amount' => scalar_int($transaction['amount'] ?? 0),
        'requested_amount' => scalar_int($transaction['requested_amount'] ?? 0),
        'unique_code' => scalar_int($transaction['unique_code'] ?? 0),
        'creation_fee' => scalar_int($transaction['creation_fee'] ?? 0),
        'balance_remaining' => scalar_int($transaction['balance_remaining'] ?? 0),
        'expires_at' => scalar_string($transaction['expires_at'] ?? ''),
        'provider' => scalar_string($transaction['provider'] ?? ''),
        'created_at' => scalar_string($transaction['created_at'] ?? ''),
        'checked_at' => scalar_string($transaction['checked_at'] ?? ''),
        'payment' => public_payment($transaction['payment'] ?? null),
    ];
}

function public_payment(mixed $payment): ?array
{
    if (!is_array($payment)) {
        return null;
    }

    return [
        'transaction_id' => scalar_string($payment['transactionId'] ?? $payment['transaction_id'] ?? ''),
        'amount' => scalar_int($payment['amount'] ?? 0),
        'status' => scalar_string($payment['status'] ?? 'success'),
        'time' => scalar_string($payment['time'] ?? ''),
        'timestamp' => scalar_int($payment['timestamp'] ?? 0),
        'issuer' => scalar_string($payment['issuer'] ?? ''),
        'provider' => scalar_string($payment['provider'] ?? ''),
    ];
}
