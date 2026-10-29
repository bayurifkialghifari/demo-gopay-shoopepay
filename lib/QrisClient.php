<?php
declare(strict_types=1);

final class QrisApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 502,
        public readonly ?int $upstreamStatus = null,
    ) {
        parent::__construct($message);
    }
}

final class QrisClient
{
    private readonly string $baseUrl;

    public function __construct(private readonly string $apiToken)
    {
        $configuredBaseUrl = getenv('QRIS_API_BASE_URL') ?: 'https://qris.karuhundeveloper.com';
        if (!preg_match('#^https?://#i', $configuredBaseUrl)) {
            throw new InvalidArgumentException('QRIS_API_BASE_URL harus berupa URL HTTP(S).');
        }
        $this->baseUrl = rtrim($configuredBaseUrl, '/');
    }

    public function account(): array
    {
        return $this->requestJson('GET', '/v1/account');
    }

    public function createQris(int $amount, ?string $provider, string $idempotencyKey): array
    {
        $payload = ['amount' => $amount];
        if ($provider !== null) {
            $payload['provider'] = $provider;
        }

        return $this->requestJson('POST', '/v1/qris/create', $payload, [
            'Idempotency-Key: ' . $idempotencyKey,
        ]);
    }

    public function checkPayment(string $transactionToken): array
    {
        return $this->requestJson('POST', '/v1/payment/check', [
            'token' => $transactionToken,
            'wait' => false,
        ]);
    }

    public function fetchQrImage(string $id): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{6,160}$/', $id)) {
            throw new QrisApiException('Referensi QRIS tidak valid.', 400);
        }

        $response = $this->requestRaw('GET', '/qr/' . rawurlencode($id), null, [
            'Accept: image/png, image/svg+xml, image/*',
            'x-api-key: ' . $this->apiToken,
            'User-Agent: QRIS-Lab-Demo/1.0',
        ], 20);
        if ($response['body'] === false) {
            throw new QrisApiException('Gambar QRIS belum dapat dimuat. Coba lagi sebentar.');
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new QrisApiException('Gambar QRIS tidak tersedia.', $response['status'] === 404 ? 404 : 502, $response['status']);
        }
        if (!str_starts_with(strtolower($response['content_type']), 'image/')) {
            throw new QrisApiException('Respons gambar QRIS tidak valid.');
        }

        return ['body' => $response['body'], 'content_type' => $response['content_type']];
    }

    private function requestJson(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiToken,
            'User-Agent: QRIS-Lab-Demo/1.0',
        ], $extraHeaders);

        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        $response = $this->requestRaw($method, $path, $body, $headers, 25);
        $raw = $response['body'];
        $status = $response['status'];

        if ($raw === false) {
            throw new QrisApiException('API QRIS tidak dapat dijangkau. Periksa koneksi lalu coba lagi.');
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new QrisApiException('API QRIS mengirim respons yang tidak valid.');
        }

        if (!is_array($decoded)) {
            throw new QrisApiException('API QRIS mengirim respons yang tidak valid.');
        }

        if ($status < 200 || $status >= 300 || ($decoded['success'] ?? true) === false) {
            throw new QrisApiException($this->messageForFailure($status, $decoded), $this->localStatus($status), $status);
        }

        return $decoded;
    }

    /** @return array{body: string|false, status: int, content_type: string} */
    private function requestRaw(string $method, string $path, ?string $body, array $headers, int $timeout): array
    {
        $url = $this->baseUrl . $path;
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return ['body' => false, 'status' => 0, 'content_type' => ''];
            }
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 7,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ];
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($curl, $options);
            $responseBody = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $error = curl_error($curl);
            curl_close($curl);

            return ['body' => $error === '' ? $responseBody : false, 'status' => $status, 'content_type' => $contentType];
        }

        $httpOptions = [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'protocol_version' => 1.1,
        ];
        if ($body !== null) {
            $httpOptions['content'] = $body;
        }
        $context = stream_context_create([
            'http' => $httpOptions,
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        $contentType = '';
        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
                $status = (int) $matches[1];
            } elseif (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, strlen('Content-Type:')));
            }
        }

        return ['body' => $responseBody, 'status' => $status, 'content_type' => $contentType];
    }

    private function localStatus(int $upstreamStatus): int
    {
        return match ($upstreamStatus) {
            400, 401, 403, 404, 409, 422, 429 => $upstreamStatus,
            default => 502,
        };
    }

    private function messageForFailure(int $status, array $decoded): string
    {
        $candidate = $decoded['message'] ?? $decoded['error'] ?? null;
        if (is_string($candidate) && $candidate !== '') {
            $candidate = str_replace($this->apiToken, '[REDACTED]', $candidate);
            return substr(strip_tags($candidate), 0, 240);
        }

        return match ($status) {
            400, 422 => 'Data request ditolak API QRIS. Periksa nominal dan provider.',
            401, 403 => 'API token ditolak. Periksa token lalu hubungkan kembali.',
            404 => 'Data QRIS tidak ditemukan atau sudah kedaluwarsa.',
            409 => 'Request QRIS sedang diproses. Coba cek status kembali.',
            429 => 'Terlalu banyak request. Tunggu sebentar sebelum mencoba lagi.',
            default => 'API QRIS sedang bermasalah. Coba lagi sebentar.',
        };
    }
}
