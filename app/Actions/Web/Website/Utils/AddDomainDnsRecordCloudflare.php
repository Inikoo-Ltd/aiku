<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jan 2024 20:52:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Utils;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AddDomainDnsRecordCloudflare
{
    use AsAction;
    use WithAttributes;

    public function handle(string $zoneId, array $dnsRecord): PromiseInterface|Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.config('app.cloudflare_api_token'),
        ])->post(config('app.cloudflare_api_url')."/zones/{$zoneId}/dns_records", [
            'type' => $dnsRecord['type'],
            'name' => $dnsRecord['name'],
            'content' => $dnsRecord['content'],
            'ttl' => $dnsRecord['ttl'] ?? 1,
            'proxied' => $dnsRecord['proxied'],
        ]);
    }
}
