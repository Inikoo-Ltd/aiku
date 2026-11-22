<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Documents received on SOFTIP Digitálny poštár (in-transaction endpoints). Uses the same config and call log
 * (e_invoice_api_calls) as SoftipEInvoiceProvider; document bodies are logged as size and sha256 only.
 */
class SoftipInbox
{
    public const array PENDING_STATES = ['NEW', 'READ', 'DOWNLOADED'];

    public const int MAX_PAGES = 50;

    public function __construct(private readonly SoftipEInvoiceProvider $provider)
    {
    }

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * Every transaction not yet acknowledged, all pages read before anything is acknowledged.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        $path         = '/api/v1/in-transaction/by-status/'.rawurlencode($this->provider->organizationId());
        $transactions = [];
        $pageToken    = null;
        $seenTokens   = [];

        do {
            $query = implode('&', array_map(fn (string $state) => 'status='.$state, self::PENDING_STATES)).'&limit=100'
                .($pageToken ? '&pageToken='.rawurlencode($pageToken) : '');
            $body  = json_decode($this->call('GET', "$path?$query")->body(), true);

            $page = is_array($body) ? ($body['transactions'] ?? null) : null;
            if (!is_array($page)) {
                throw new EInvoiceProviderException('SOFTIP in-transaction list is not readable');
            }
            array_push($transactions, ...$page);

            $pageToken = $body['nextPageToken'] ?? null;
            if (!$page || isset($seenTokens[$pageToken]) || count($seenTokens) >= self::MAX_PAGES) {
                break;
            }
            $seenTokens[$pageToken] = true;
        } while ($pageToken);

        return $transactions;
    }

    public function downloadXml(string $transactionId): string
    {
        return $this->call('GET', '/api/v1/in-transaction/download-doc/'.rawurlencode($transactionId), binary: true)->body();
    }

    public function downloadPdf(string $transactionId): string
    {
        return $this->call('GET', '/api/v1/in-transaction/download-pdf/'.rawurlencode($transactionId), binary: true)->body();
    }

    public function acknowledge(string $transactionId): void
    {
        $this->call('PUT', '/api/v1/in-transaction/acknowledge/'.rawurlencode($transactionId));
    }

    private function call(string $method, string $pathAndQuery, bool $binary = false): Response
    {
        if (!$this->isConfigured()) {
            throw new EInvoiceProviderException('SOFTIP API key is not set', 401);
        }

        $startedAt = microtime(true);
        $request   = Http::baseUrl(rtrim((string)config('services.softip.url'), '/'))
            ->withHeaders(['X-API-KEY' => (string)config('services.softip.api_key')])
            ->accept($binary ? '*/*' : 'application/json')
            ->connectTimeout(10)
            ->timeout(120);

        try {
            $response = $method === 'PUT' ? $request->put($pathAndQuery) : $request->get($pathAndQuery);
        } catch (ConnectionException $exception) {
            $this->log($method, $pathAndQuery, null, $exception->getMessage(), $startedAt);

            throw new EInvoiceProviderException('SOFTIP could not be reached: '.$exception->getMessage());
        }

        $logged = $binary && $response->successful()
            ? json_encode(['bytes' => strlen($response->body()), 'sha256' => hash('sha256', $response->body()), 'content_type' => $response->header('Content-Type')])
            : $response->body();
        $this->log($method, $pathAndQuery, $response->status(), $logged, $startedAt);

        if ($response->failed()) {
            throw new EInvoiceProviderException("SOFTIP $method $pathAndQuery: HTTP ".$response->status(), $response->status(), ['body' => Str::limit($response->body(), 2000)]);
        }

        return $response;
    }

    private function log(string $method, string $pathAndQuery, ?int $status, ?string $response, float $startedAt): void
    {
        $apiKey = (string)config('services.softip.api_key');

        DB::table('e_invoice_api_calls')->insert([
            'provider'        => 'softip',
            'method'          => $method,
            'path'            => Str::limit($pathAndQuery, 250, ''),
            'request'         => null,
            'response_status' => $status,
            'response'        => $apiKey !== '' && $response !== null ? str_replace($apiKey, '***', $response) : $response,
            'duration_ms'     => (int)round((microtime(true) - $startedAt) * 1000),
            'created_at'      => now(),
        ]);
    }
}
