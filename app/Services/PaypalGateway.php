<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaypalGateway
{
    private ?string $token = null;

    public function ready(): bool
    {
        $mode = config('services.paypal.mode');
        return config('services.paypal.enabled') && in_array($mode, ['sandbox', 'live'], true)
            && ($mode !== 'live' || app()->environment('production'))
            && config('services.paypal.client_id') && config('services.paypal.secret')
            && config('services.paypal.merchant_id');
    }

    public function request(string $method, string $path, ?array $data = null, ?string $requestId = null): array
    {
        if (! $this->ready()) {
            throw new \RuntimeException('PayPal is not configured.');
        }
        $base = config('services.paypal.mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        if ($this->token === null) {
            $response = Http::timeout(20)->acceptJson()->asForm()
                ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
                ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new \RuntimeException('PayPal authentication unavailable.');
            }
            $this->token = $response->json('access_token');
        }
        $client = Http::timeout(25)->acceptJson()->withToken($this->token);
        if ($requestId) {
            $client = $client->withHeaders(['PayPal-Request-Id' => $requestId, 'Prefer' => 'return=representation']);
        }
        $options = $method === 'GET' ? [] : ['json' => $data ?? new \stdClass()];
        $response = $client->send($method, $base.$path, $options);
        if (! $response->successful() || ! is_array($response->json())) {
            // Provider responses and credentials must not appear in public errors or logs.
            throw new \RuntimeException('PayPal request unavailable.');
        }
        return $response->json();
    }
}
