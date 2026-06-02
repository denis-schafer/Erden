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

        return $response->json()['data']['terminals'] ?? [];
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
                'payment_method' => [
                    'default_type' => 'credit_card',
                ],
            ],
            'description' => $description,
        ];

        $idempotencyKey = (string) Str::uuid();

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl . '/v1/orders', $payload);

        if ($response->failed()) {
            Log::error('[MercadoPagoPoint] Error creating order', [
                'status' => $response->status(),
                'body' => $response->body(),
                'terminal_id' => $terminalId,
                'amount' => $amount,
            ]);
            return [
                'success' => false,
                'error' => $response->json()['message'] ?? $response->body(),
            ];
        }

        $data = $response->json();

        Log::info('[MercadoPagoPoint] Order created', [
            'order_id' => $data['id'] ?? null,
            'external_reference' => $externalReference,
            'status' => $data['status'] ?? null,
        ]);

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function getOrder(string $orderId): array
    {
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

        return $response->json();
    }

    public function cancelOrder(string $orderId): array
    {
        $idempotencyKey = (string) Str::uuid();

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

        return $response->json();
    }
}
