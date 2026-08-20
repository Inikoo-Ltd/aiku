<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class PurgeCloudflareUrl
{
    use AsAction;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website, string $path): bool
    {
        $response = Http::withToken(decrypt($website->cloudflare_token))
            ->acceptJson()
            ->post("https://api.cloudflare.com/client/v4/zones/$website->cloudflare_zone_id/purge_cache", [
                'files' => [
                    "https://$website->domain$path",
                    "https://www.$website->domain$path",
                ],
            ]);

        return (bool) $response->json('success', false);
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:purge {path=/favicon.ico} {--website=}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): void
    {
        $path     = $command->argument('path');
        $websites = Website::where('status', true)
            ->whereNotNull('cloudflare_zone_id')
            ->whereNotNull('cloudflare_token')
            ->when($command->option('website'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($websites as $website) {
            $purged = $this->handle($website, $path);
            $command->line(($purged ? '<info>purged</info>  ' : '<error>failed</error>  ').$website->domain.$path);
        }
    }
}
