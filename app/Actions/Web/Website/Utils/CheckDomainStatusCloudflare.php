<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jan 2024 20:52:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Utils;

use App\Enums\Web\Website\WebsiteCloudflareStatusEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckDomainStatusCloudflare
{
    use AsAction;

    public string $commandSignature = 'domain:check-cloudflare-status';

    public function handle(): void
    {
        $domains = Website::where('cloudflare_status', WebsiteCloudflareStatusEnum::PENDING->value)->get();

        foreach ($domains as $domain) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.config('app.cloudflare_api_token'),
            ])->get(config('app.cloudflare_api_url')."/zones/$domain->cloudflare_id")->json();

            Website::where('cloudflare_id', $response['result']['id'])->update([
                'cloudflare_status' => $response['result']['status'],
            ]);
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
