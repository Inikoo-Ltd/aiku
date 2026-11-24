<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SOFTIP Digitálny poštár public API (Swagger: /swagger/v1/swagger.json). Every call and its answer is
 * written to e_invoice_api_calls without the API key.
 */
class SoftipEInvoiceProvider implements EInvoiceProvider
{
    public function name(): string
    {
        return 'softip';
    }

    public function isConfigured(): bool
    {
        return (string)config('services.softip.api_key') !== '' && (string)config('services.softip.url') !== '';
    }

    public function send(string $xml, string $idempotencyKey, string $tag): array
    {
        $body = $this->call(
            'POST',
            '/api/v1/document/send',
            fn (PendingRequest $request) => $request
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->attach('File', $xml, Str::slug($tag).'.xml', ['Content-Type' => 'application/xml'])
                ->post('/api/v1/document/send', ['Tag' => $tag]),
            ['idempotency_key' => $idempotencyKey, 'tag' => $tag, 'file_sha256' => hash('sha256', $xml), 'file_bytes' => strlen($xml)]
        );

        $status = (string)($body['status'] ?? '');

        return [
            'transaction_id' => (string)$body['transactionId'],
            'status'         => preg_match('/^[A-Za-z_]+$/', $status) ? strtoupper($status) : 'NEW',
            'checksum'       => $body['checksum'] ?? null,
        ];
    }

    public function status(string $transactionId): array
    {
        $path = '/api/v1/out-transaction/'.rawurlencode($transactionId);
        $body = $this->call('GET', $path, fn (PendingRequest $request) => $request->get($path, ['events' => 'true']));

        return ['status' => strtoupper((string)$body['status']), 'details' => $body];
    }

    public function findByTag(string $tag): array
    {
        $path         = '/api/v1/out-transaction/by-state/'.$this->organizationId();
        $transactions = [];
        $pageToken    = null;

        do {
            $query = array_filter(['tag' => $tag, 'limit' => 500, 'pageToken' => $pageToken]);
            $body  = $this->call('GET', $path, fn (PendingRequest $request) => $request->get($path, $query), $query);

            foreach ($body['transactions'] ?? [] as $transaction) {
                $transactions[] = ['transaction_id' => (string)$transaction['id'], 'status' => strtoupper((string)$transaction['status']), 'tag' => $transaction['tag'] ?? null];
            }
            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken);

        return $transactions;
    }

    public function validate(string $xml): array
    {
        $body = $this->call(
            'POST',
            '/api/v1/document/validate',
            fn (PendingRequest $request) => $request->attach('File', $xml, 'document.xml', ['Content-Type' => 'application/xml'])->post('/api/v1/document/validate'),
            ['file_sha256' => hash('sha256', $xml), 'file_bytes' => strlen($xml)]
        );

        return ['is_valid' => (bool)($body['isValid'] ?? false), 'issues' => $body['issues'] ?? []];
    }

    public function lookupParticipant(string $peppolId): bool
    {
        $path = '/api/v1/organization/sml-lookup';
        $body = $this->call('GET', $path, fn (PendingRequest $request) => $request->get($path, ['peppolId' => $peppolId]), ['peppolId' => $peppolId]);

        return (bool)($body['Registered'] ?? $body['registered'] ?? false);
    }

    public function organizationId(): string
    {
        if ($organizationId = (string)config('services.softip.organization_id')) {
            return $organizationId;
        }

        return Cache::remember('softip-organization-id:'.hash('sha256', (string)config('services.softip.api_key')), now()->addDay(), function () {
            $path = '/api/v1/organization/logged-user';
            $body = $this->call('GET', $path, fn (PendingRequest $request) => $request->get($path));

            $organizations = $body['organizations'] ?? [];
            if (count($organizations) !== 1 || empty($organizations[0]['id'])) {
                throw new EInvoiceProviderException('SOFTIP returned '.count($organizations).' organisations for this API key, set SOFTIP_ORGANIZATION_ID');
            }

            return (string)$organizations[0]['id'];
        });
    }

    /**
     * @param callable(PendingRequest): Response $send
     * @param array<string, mixed> $request what is logged as the request, never the key
     *
     * @return array<string, mixed>
     */
    public function call(string $method, string $path, callable $send, array $request = []): array
    {
        if (!$this->isConfigured()) {
            throw new EInvoiceProviderException('SOFTIP URL or API key is not set', 401);
        }

        $startedAt = microtime(true);
        $response  = null;

        try {
            $response = $send(
                Http::baseUrl(rtrim((string)config('services.softip.url'), '/'))
                    ->withHeaders(['X-API-KEY' => (string)config('services.softip.api_key')])
                    ->acceptJson()
                    ->connectTimeout(10)
                    ->timeout(60)
            );
        } catch (ConnectionException $exception) {
            $this->log($method, $path, $request, null, $exception->getMessage(), $startedAt);

            throw new EInvoiceProviderException('SOFTIP could not be reached: '.$exception->getMessage());
        }

        $this->log($method, $path, $request, $response->status(), $response->body(), $startedAt);

        $body = $response->json();
        if ($response->failed()) {
            $errors  = collect(is_array($body) ? ($body['errors'] ?? []) : [])->pluck('message')->filter()->implode('; ');
            $message = $errors ?: ('HTTP '.$response->status());

            throw new EInvoiceProviderException("SOFTIP $method $path: $message", $response->status(), is_array($body) ? $body : ['body' => Str::limit($response->body(), 2000)]);
        }

        return is_array($body) ? $body : [];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function log(string $method, string $path, array $request, ?int $status, ?string $response, float $startedAt): void
    {
        $apiKey = (string)config('services.softip.api_key');

        DB::table('e_invoice_api_calls')->insert([
            'provider'        => $this->name(),
            'method'          => $method,
            'path'            => $path,
            'request'         => $request ? json_encode($request) : null,
            'response_status' => $status,
            'response'        => $apiKey !== '' ? str_replace($apiKey, '***', (string)$response) : $response,
            'duration_ms'     => (int)round((microtime(true) - $startedAt) * 1000),
            'created_at'      => now(),
        ]);
    }
}
