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


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website, ?string $token = null): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.($token ?: decrypt($website->cloudflare_token)),
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
            }
        }

        return $zoneId;
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:get-zone-id {website}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): void
    {
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        $this->handle($website);
    }
}
