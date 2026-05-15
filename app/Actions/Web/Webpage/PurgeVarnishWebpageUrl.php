<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 May 2026 23:13:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PurgeVarnishWebpageUrl extends OrgAction
{
    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Webpage $webpage, ?Command $command = null): void
    {
        $canonicalUrl = parse_url($webpage->canonical_url);

        $host = strtolower($canonicalUrl['host']);
        $path = $canonicalUrl['path'] ?? '/';
        $webpagePath = Str::start($webpage->url, '/');

        foreach (config('iris.cache.varnish_hosts') as $varnishHost) {
            if (!$varnishHost) {
                continue;
            }

            $response = Http::withHeaders([
                'x-ban-host' => $host,
                'x-ban-url'  => $path,
            ])->send('BAN', $varnishHost);

            $command?->line("BAN response for $host$path via $varnishHost: ".$response->status().' - '.$response->body());

            if ($webpagePath != $path) {
                $response = Http::withHeaders([
                    'x-ban-host' => $host,
                    'x-ban-url'  => $webpagePath,
                ])->send('BAN', $varnishHost);

                $command?->line("BAN response for $host$webpagePath via $varnishHost: ".$response->status().' - '.$response->body());
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'varnish:purge:webpage {slug}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        $webpage = Webpage::where('slug', $command->argument('slug'))->firstOrFail();
        $this->handle($webpage, $command);

        return 0;
    }

}
