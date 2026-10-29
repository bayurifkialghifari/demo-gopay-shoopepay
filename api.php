<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/QrisClient.php';

boot_session();
$request = require_json_post();
require_csrf();

$action = scalar_string($request['action'] ?? '');

try {
    switch ($action) {
        case 'state':
            json_response(['success' => true, 'state' => current_state()]);

        case 'save_token':
            save_token($request);
            break;

        case 'clear_token':
            unset(
                $_SESSION[QRIS_SESSION_TOKEN_KEY],
                $_SESSION[QRIS_SESSION_ACCOUNT_KEY],
                $_SESSION[QRIS_SESSION_TRANSACTION_KEY]
            );
            session_regenerate_id(true);
            json_response([
                'success' => true,
                'message' => 'API token sudah dihapus dari session.',
                'state' => current_state(),
            ]);

        case 'account':
            $client = new QrisClient(require_api_token());
            $account = public_account($client->account());
            $_SESSION[QRIS_SESSION_ACCOUNT_KEY] = $account;
            json_response([
                'success' => true,
                'account' => $account,
                'state' => current_state(),
            ]);

        case 'create':
            create_qris($request);
            break;

        case 'check':
            check_payment();
            break;

        case 'reset_transaction':
            unset($_SESSION[QRIS_SESSION_TRANSACTION_KEY]);
            json_response([
                'success' => true,
                'message' => 'Transaksi aktif sudah direset.',
                'state' => current_state(),
            ]);

        default:
            json_response(['success' => false, 'message' => 'Aksi API tidak dikenali.'], 400);
    }
} catch (QrisApiException $exception) {
    json_response([
        'success' => false,
        'message' => $exception->getMessage(),
        'code' => $exception->upstreamStatus,
    ], $exception->httpStatus);
} catch (Throwable $exception) {
    error_log(sprintf('QRIS Lab error [%s]: %s', $exception::class, $exception->getMessage()));
    json_response([
        'success' => false,
        'message' => 'Terjadi kesalahan di server. Coba lagi sebentar.',
    ], 500);
}

function save_token(array $request): never
{
    $token = trim(scalar_string($request['token'] ?? ''));
    if (strlen($token) < 8 || strlen($token) > 512 || preg_match('/[\x00-\x1F\x7F]/', $token)) {
        json_response([
            'success' => false,
            'message' => 'Masukkan API token yang valid.',
        ], 422);
    }

    $client = new QrisClient($token);
    $account = public_account($client->account());
    $_SESSION[QRIS_SESSION_TOKEN_KEY] = $token;
    $_SESSION[QRIS_SESSION_ACCOUNT_KEY] = $account;
    unset($_SESSION[QRIS_SESSION_TRANSACTION_KEY]);
    session_regenerate_id(true);

    json_response([
        'success' => true,
        'message' => 'API token terhubung dan disimpan di session server.',
        'state' => current_state(),
    ]);
}

function create_qris(array $request): never
{
    $amountValue = $request['amount'] ?? null;
    if ((!is_int($amountValue) && !is_string($amountValue)) || filter_var($amountValue, FILTER_VALIDATE_INT) === false) {
        json_response(['success' => false, 'message' => 'Nominal harus berupa bilangan bulat dalam Rupiah.'], 422);
    }

    $amount = (int) $amountValue;
    if ($amount < 1 || $amount > 100_000_000) {
        json_response(['success' => false, 'message' => 'Nominal harus berada di antara Rp1 dan Rp100.000.000.'], 422);
    }

    $providerValue = scalar_string($request['provider'] ?? '');
    $provider = $providerValue === '' ? null : strtolower($providerValue);
    if ($provider !== null && !in_array($provider, ['gopay', 'shopee'], true)) {
        json_response(['success' => false, 'message' => 'Provider yang dipilih tidak didukung.'], 422);
    }

    $client = new QrisClient(require_api_token());
    $response = $client->createQris($amount, $provider, bin2hex(random_bytes(24)));
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $id = scalar_string($data['id'] ?? '');
    $token = scalar_string($data['token'] ?? '');
    if ($id === '' || $token === '') {
        throw new QrisApiException('API QRIS tidak mengirim referensi transaksi yang lengkap.');
    }

    $expiresAt = scalar_string($data['expires_at'] ?? '');
    if ($expiresAt === '' && scalar_int($data['expires_in_seconds'] ?? 0) > 0) {
        $expiresAt = gmdate(DATE_ATOM, time() + scalar_int($data['expires_in_seconds']));
    }

    $transaction = [
        'id' => $id,
        'token' => $token,
        'status' => 'pending',
        'paid' => false,
        'amount' => scalar_int($data['amount'] ?? $amount),
        'requested_amount' => scalar_int($data['requested_amount'] ?? $amount),
        'unique_code' => scalar_int($data['unique_code'] ?? 0),
        'creation_fee' => scalar_int($data['creation_fee'] ?? 0),
        'balance_remaining' => scalar_int($data['balance_remaining'] ?? 0),
        'expires_at' => $expiresAt,
        'provider' => scalar_string($data['provider'] ?? $provider ?? ''),
        'created_at' => gmdate(DATE_ATOM),
        'checked_at' => '',
        'payment' => null,
    ];
    $_SESSION[QRIS_SESSION_TRANSACTION_KEY] = $transaction;

    if (isset($_SESSION[QRIS_SESSION_ACCOUNT_KEY]) && is_array($_SESSION[QRIS_SESSION_ACCOUNT_KEY])) {
        if (array_key_exists('balance_remaining', $data)) {
            $_SESSION[QRIS_SESSION_ACCOUNT_KEY]['balance'] = scalar_int($data['balance_remaining']);
        }
    }

    json_response([
        'success' => true,
        'message' => 'QRIS berhasil dibuat dan siap dipindai.',
        'transaction' => public_transaction($transaction),
        'state' => current_state(),
    ]);
}

function check_payment(): never
{
    $stored = $_SESSION[QRIS_SESSION_TRANSACTION_KEY] ?? null;
    if (!is_array($stored) || scalar_string($stored['token'] ?? '') === '') {
        json_response(['success' => false, 'message' => 'Belum ada transaksi QRIS yang dapat diperiksa.'], 409);
    }

    $client = new QrisClient(require_api_token());
    $response = $client->checkPayment((string) $stored['token']);
    $paid = scalar_bool($response['paid'] ?? false);
    $status = scalar_string($response['status'] ?? ($paid ? 'success' : 'pending'));
    if (!in_array($status, ['pending', 'success', 'expired'], true)) {
        $status = $paid ? 'success' : 'pending';
    }

    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $payment = $response['transaction'] ?? $data['transaction'] ?? null;
    $stored['paid'] = $paid;
    $stored['status'] = $status;
    $stored['checked_at'] = gmdate(DATE_ATOM);
    $stored['provider'] = scalar_string($response['provider'] ?? $data['provider'] ?? $stored['provider'] ?? '');
    if (scalar_int($data['amount'] ?? 0) > 0) {
        $stored['amount'] = scalar_int($data['amount']);
    }
    if (scalar_string($data['expires_at'] ?? '') !== '') {
        $stored['expires_at'] = scalar_string($data['expires_at']);
    }
    if (is_array($payment)) {
        $stored['payment'] = $payment;
    }
    $_SESSION[QRIS_SESSION_TRANSACTION_KEY] = $stored;

    json_response([
        'success' => true,
        'message' => match ($status) {
            'success' => 'Pembayaran sudah diterima.',
            'expired' => 'QRIS sudah kedaluwarsa.',
            default => 'Pembayaran belum ditemukan.',
        },
        'check' => [
            'paid' => $paid,
            'status' => $status,
            'provider' => $stored['provider'],
            'payment' => public_payment($payment),
        ],
        'transaction' => public_transaction($stored),
        'state' => current_state(),
    ]);
}

function current_state(): array
{
    $token = session_api_token();
    $account = $_SESSION[QRIS_SESSION_ACCOUNT_KEY] ?? null;
    $transaction = $_SESSION[QRIS_SESSION_TRANSACTION_KEY] ?? null;

    return [
        'connected' => $token !== null,
        'token_masked' => $token !== null ? mask_secret($token) : '',
        'account' => is_array($account) ? $account : null,
        'transaction' => is_array($transaction) ? public_transaction($transaction) : null,
    ];
}
