<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 12:29:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PurgeVarnishPath extends OrgAction
{
    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website, string $path): void
    {
        $host = strtolower('www.'.$website->domain);
        $path = Str::start($path, '/');

        foreach (config('iris.cache.varnish_hosts') as $varnishHost) {
            if (!$varnishHost) {
                continue;
            }

            Http::withHeaders([
                'x-ban-host' => $host,
                'x-ban-url'  => $path,
            ])->send('BAN', $varnishHost);
        }
    }

}
