<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/QrisClient.php';

boot_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

$token = session_api_token();
$transaction = $_SESSION[QRIS_SESSION_TRANSACTION_KEY] ?? null;
if ($token === null || !is_array($transaction)) {
    http_response_code(404);
    exit;
}

$id = scalar_string($transaction['id'] ?? '');
if ($id === '') {
    http_response_code(404);
    exit;
}

try {
    $image = (new QrisClient($token))->fetchQrImage($id);
    header('Content-Type: ' . $image['content_type']);
    header('Cache-Control: private, no-store, max-age=0');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('X-Content-Type-Options: nosniff');
    echo $image['body'];
} catch (QrisApiException $exception) {
    http_response_code($exception->httpStatus === 404 ? 404 : 502);
}
