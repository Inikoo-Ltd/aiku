<?php

namespace App\Actions\Accounting\PaymentGateway\Btree\Traits;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithBtreeConfiguration
{
    protected function btreeClient(PaymentAccount $paymentAccount): PendingRequest
    {
        [$clientId, $clientSecret] = $this->getBtreeCredentials($paymentAccount);

        return Http::baseUrl($this->getBtreeBaseUrl())
            ->withBasicAuth($clientId, $clientSecret)
            ->withHeaders([
                'Braintree-Version' => '2019-01-01',
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
            ])
            ->retry(2, 500);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function getBtreeCredentials(PaymentAccount $paymentAccount): array
    {
        if (!app()->isProduction() && config('app.sandbox.btree.client_id')) {
            return [
                config('app.sandbox.btree.client_id'),
                config('app.sandbox.btree.client_secret', ''),
            ];
        }

        return [
            Arr::get($paymentAccount->data, 'braintree_client_id') ?: Arr::get($paymentAccount->data, 'credentials.client_id', ''),
            Arr::get($paymentAccount->data, 'braintree_client_secret') ?: Arr::get($paymentAccount->data, 'credentials.client_secret', ''),
        ];
    }

    protected function getBtreeBaseUrl(): string
    {
        return app()->isProduction()
            ? config('services.btree.base_url', 'https://payments.braintree-api.com')
            : config('services.btree.sandbox_url', 'https://payments.sandbox.braintree-api.com');
    }

    /**
     * @return array<string, mixed>
     */
    protected function btreeGraphql(PaymentAccount $paymentAccount, string $query, array $variables = []): array
    {
        return $this->btreeHandleResponse(
            $this->btreeClient($paymentAccount)->post('/graphql', [
                'query'     => $query,
                'variables' => $variables,
            ])
        );
    }

    private function btreeHandleResponse(Response $response): array
    {
        $body = $response->json() ?? [];

        if ($response->successful() && !Arr::has($body, 'errors')) {
            return Arr::get($body, 'data', []);
        }

        $message = Arr::get($body, 'errors.0.message', 'Braintree API error');

        throw new \RuntimeException("[Btree] HTTP {$response->status()}: {$message}");
    }
}
