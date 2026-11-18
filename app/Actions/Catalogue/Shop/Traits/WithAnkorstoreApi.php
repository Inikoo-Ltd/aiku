<?php

namespace App\Actions\Catalogue\Shop\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait WithAnkorstoreApi
{
    public function hasAnkorstore(): bool
    {
        return (bool)Arr::get($this->settings, 'ankorstore.client_secret');
    }

    /**
     * Ankorstore is JSON:API with a client credentials token that lasts an hour. A failed request throws, so a caller
     * never mistakes a refused call for an empty answer. The settings travel with every copy of the production
     * database, so a copy only reads: orders are accepted and shipped from production or the sandbox.
     */
    public function ankorstoreRequest(string $method, string $path, array $payload = []): array
    {
        if ($method !== 'GET' && !app()->isProduction() && !app()->runningUnitTests() && !Arr::get($this->settings, 'ankorstore.sandbox')) {
            throw new RuntimeException("Ankorstore is changed only from production or its sandbox, not $method $path");
        }

        $request = Http::timeout(60)
            ->withToken($this->ankorstoreToken())
            ->accept('application/vnd.api+json')
            ->withHeaders(['Content-Type' => 'application/vnd.api+json']);

        $url      = $this->ankorstoreBaseUrl().'/api/v1/'.ltrim($path, '/');
        $response = $method === 'GET' ? $request->get($url) : $request->send($method, $url, ['json' => $payload]);

        if (!$response->successful()) {
            throw new RuntimeException('Ankorstore API '.$response->status().' '.$method.' '.$path.': '.mb_substr($response->body(), 0, 500));
        }

        return (array)$response->json();
    }

    public function ankorstoreBaseUrl(): string
    {
        return Arr::get($this->settings, 'ankorstore.sandbox') ? 'https://www.public.ankorstore-sandbox.com' : 'https://www.ankorstore.com';
    }

    private function ankorstoreToken(): string
    {
        return Cache::remember('ankorstore-token:'.$this->id.':'.md5((string)Arr::get($this->settings, 'ankorstore.client_id')), 3000, function () {
            $response = Http::timeout(30)->acceptJson()->asForm()->post($this->ankorstoreBaseUrl().'/oauth/token', [
                'grant_type'    => 'client_credentials',
                'client_id'     => Arr::get($this->settings, 'ankorstore.client_id'),
                'client_secret' => Arr::get($this->settings, 'ankorstore.client_secret'),
                'scope'         => '*',
            ]);

            if (!$response->successful() || !$response->json('access_token')) {
                throw new RuntimeException('Ankorstore refused the credentials: '.$response->status().' '.mb_substr($response->body(), 0, 300));
            }

            return (string)$response->json('access_token');
        });
    }
}
