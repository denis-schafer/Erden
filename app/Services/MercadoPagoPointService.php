<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoPointService
{
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct(
        private string $accessToken
    ) {}

    public function getDevices(): array
    {
        Log::info('[MercadoPagoPoint] Fetching devices from MP API', [
            'token_prefix' => substr($this->accessToken, 0, 10) . '...',
        ]);

        $response = Http::withToken($this->accessToken)
            ->get($this->baseUrl . '/terminals/v1/list', [
                'limit' => 50,
                'offset' => 0,
            ]);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error fetching devices', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $data = $response->json();
        $terminals = $data['data']['terminals'] ?? [];

        Log::info('[MercadoPagoPoint] Devices fetched successfully', [
            'count' => count($terminals),
            'terminals' => collect($terminals)->map(fn($t) => [
                'id' => $t['id'] ?? null,
                'name' => $t['name'] ?? null,
                'status' => $t['status'] ?? null,
                'model' => $t['model'] ?? null,
                'operating_mode' => $t['operating_mode'] ?? null,
            ])->toArray(),
        ]);

        return $terminals;
    }

    public function getTerminalInfo(string $terminalId): array
    {
        Log::info('[MercadoPagoPoint] Fetching terminal info', [
            'terminal_id' => $terminalId,
            'token_prefix' => substr($this->accessToken, 0, 10) . '...',
        ]);

        // Intentar obtener info del terminal desde la lista de dispositivos
        $response = Http::withToken($this->accessToken)
            ->get($this->baseUrl . '/terminals/v1/list', [
                'limit' => 50,
                'offset' => 0,
            ]);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error fetching terminal list', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $data = $response->json();
        $terminals = $data['data']['terminals'] ?? [];
        $terminal = collect($terminals)->firstWhere('id', $terminalId);

        if (!$terminal) {
            Log::warning('[MercadoPagoPoint] Terminal not found in account', [
                'terminal_id' => $terminalId,
            ]);
            return [];
        }

        Log::info('[MercadoPagoPoint] Terminal info retrieved', [
            'terminal_id' => $terminalId,
            'info' => $terminal,
        ]);

        return $terminal;
    }

    public function createOrder(
        string $terminalId,
        string $amount,
        string $externalReference,
        string $description = ''
    ): array {

        $payload = [
            'type' => 'point',
            'external_reference' => $externalReference,
            'expiration_time' => 'PT15M',
            'transactions' => [
                'payments' => [
                    ['amount' => $amount],
                ],
            ],
            'config' => [
                'point' => [
                    'terminal_id' => $terminalId,
                    'print_on_terminal' => 'no_ticket',
                ],
            ],
            'description' => $description,
        ];

        $idempotencyKey = (string) Str::uuid();

        Log::info('[MercadoPagoPoint] Sending order to MP API', [
            'terminal_id' => $terminalId,
            'amount' => $amount,
            'external_reference' => $externalReference,
            'description' => $description,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'token_prefix' => substr($this->accessToken, 0, 10) . '...',
        ]);

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl . '/v1/orders', $payload);

        $responseData = $response->json();
        $httpStatus = $response->status();

        Log::info('[MercadoPagoPoint] MP API response received', [
            'http_status' => $httpStatus,
            'response_body' => $responseData,
        ]);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error creating order', [
                'status' => $httpStatus,
                'body' => $response->body(),
                'terminal_id' => $terminalId,
                'amount' => $amount,
            ]);
            return [
                'success' => false,
                'error' => $responseData['message'] ?? $response->body(),
            ];
        }

        $data = $responseData;

        Log::info('[MercadoPagoPoint] Order created', [
            'order_id' => $data['id'] ?? null,
            'external_reference' => $externalReference,
            'status' => $data['status'] ?? null,
            'full_response' => $data,
        ]);

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function getOrder(string $orderId): array
    {
        Log::info('[MercadoPagoPoint] Fetching order status', [
            'order_id' => $orderId,
            'token_prefix' => substr($this->accessToken, 0, 10) . '...',
        ]);

        $response = Http::withToken($this->accessToken)
            ->get($this->baseUrl . '/v1/orders/' . $orderId);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error fetching order', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $orderId,
            ]);
            return [];
        }

        $data = $response->json();

        Log::info('[MercadoPagoPoint] Order status fetched', [
            'order_id' => $orderId,
            'status' => $data['status'] ?? null,
            'full_response' => $data,
        ]);

        return $data;
    }

    public function cancelOrder(string $orderId): array
    {
        $idempotencyKey = (string) Str::uuid();

        Log::info('[MercadoPagoPoint] Cancelling order', [
            'order_id' => $orderId,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl . '/v1/orders/' . $orderId . '/cancel');

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error cancelling order', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $orderId,
            ]);
            return [];
        }

        $data = $response->json();

        Log::info('[MercadoPagoPoint] Order cancelled', [
            'order_id' => $orderId,
            'full_response' => $data,
        ]);

        return $data;
    }

    public function updateTerminalMode(string $terminalId, string $mode = 'PDV'): array
    {
        Log::info('[MercadoPagoPoint] Updating terminal operating mode', [
            'terminal_id' => $terminalId,
            'requested_mode' => $mode,
            'token_prefix' => substr($this->accessToken, 0, 10) . '...',
        ]);

        $payload = [
            'terminals' => [
                [
                    'id' => $terminalId,
                    'operating_mode' => $mode,
                ],
            ],
        ];

        $response = Http::withToken($this->accessToken)
            ->patch($this->baseUrl . '/terminals/v1/setup', $payload);

        $responseData = $response->json();
        $httpStatus = $response->status();

        Log::info('[MercadoPagoPoint] Update terminal mode response', [
            'http_status' => $httpStatus,
            'response_body' => $responseData,
            'terminal_id' => $terminalId,
        ]);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error updating terminal mode', [
                'status' => $httpStatus,
                'body' => $response->body(),
                'terminal_id' => $terminalId,
            ]);
            return [
                'success' => false,
                'error' => $responseData['message'] ?? $response->body(),
            ];
        }

        Log::info('[MercadoPagoPoint] Terminal mode updated successfully', [
            'terminal_id' => $terminalId,
            'response' => $responseData,
        ]);

        return [
            'success' => true,
            'data' => $responseData,
        ];
    }

    public function getAccessTokenPrefix(): string
    {
        return substr($this->accessToken, 0, 10) . '...';
    }
}
