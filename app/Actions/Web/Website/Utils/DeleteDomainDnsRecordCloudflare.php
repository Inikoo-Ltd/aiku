<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jan 2024 20:52:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Utils;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteDomainDnsRecordCloudflare
{
    use AsAction;

    public function handle(string $zoneId, string $dnsRecordId): Response|PromiseInterface
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.config('app.cloudflare_api_token'),
        ])->delete(config('app.cloudflare_api_url')."/zones/$zoneId/dns_records/$dnsRecordId");
    }
}
