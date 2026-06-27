<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 14:02:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCloudflareZoneId
{
    use asAction;


    public function handle(Website $website, ?string $token = null): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.($token ? $token : decrypt($website->cloudflare_token)),
            'Content-Type'  => 'application/json',
        ])->get('https://api.cloudflare.com/client/v4/zones', [
            'name' => $website->domain,
        ]);

        $zoneId = null;
        if ($response->successful()) {
            $data = $response->json();

            if ($data['success'] && count($data['result']) > 0) {
                $zone   = $data['result'][0];
                $zoneId = $zone['id'];
                // do whatever you need with $zoneId / $zone
                // } else {
                // no zone found, or Cloudflare returned success=false with errors
                //$errors = $data['errors'] ?? [];
                // }
                // } else {
                // request failed (4xx/5xx)
                //$errors = $response->json()['errors'] ?? $response->body();
            }
        }

        return $zoneId;
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:get-zone-id {website}';
    }

    public function asCommand(Command $command): void
    {
        $website = Website::where('slug', $command->argument('website'))->findOrFail();
        $this->handle($website);
    }
}
