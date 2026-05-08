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


class PurgeVarnishWebpageUrl extends OrgAction
{

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Webpage $webpage, ?Command $command = null): void
    {
        $url = parse_url($webpage->canonical_url);

        $host = $url['host'];
        $path = $url['path'] ?? '/';
        $url  = '/'.$webpage->url;

        foreach (config('iris.cache.varnish_hosts') as $varnishHost) {
            if (!$varnishHost) {
                continue;
            }

            $response = Http::withHeaders([
                'Host' => $host,
            ])->send('PURGE', $varnishHost.$path);

            $command?->line("Purge response for $varnishHost$path: ".$response->status().' - '.$response->body());

            if ($url != $path) {
                $response = Http::withHeaders([
                    'Host' => $host,
                ])->send('PURGE', $varnishHost.$url);

                $command?->line("Purge response for $varnishHost$url: ".$response->status().' - '.$response->body());
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
        $webpage = Webpage::where('slug', $command->argument('slug'))->first();
        $this->handle($webpage, $command);

        return 0;
    }

}
