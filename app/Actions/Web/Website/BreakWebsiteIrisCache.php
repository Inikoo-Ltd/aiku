<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakWebsiteIrisCache
{
    use AsAction;

    /**
     * Drops only the caches that hold website settings for the storefront: the layout props
     * (which carry iris_search_model) and the Website model cached per domain. Unlike
     * BreakWebsiteCache this triggers no crawl, so it is safe to run over every website.
     */
    public function handle(Website $website, ?Command $command = null): void
    {
        ClearCacheByWildcard::run("irisData:website:$website->id:*", $command);
        Cache::forget(config('iris.cache.website.prefix').'_domain:'.$website->domain);

        if (config('iris.cache.varnish')) {
            BreakWebsiteVarnishCache::run($website, $command);
        }
    }

    public function getCommandSignature(): string
    {
        return 'website:break_iris_cache {slug? : leave empty to run over every live website}';
    }

    public function asCommand(Command $command): int
    {
        $websites = Website::when(
            $command->argument('slug'),
            fn ($query) => $query->where('slug', $command->argument('slug')),
            fn ($query) => $query->where('status', true)
        )->get();

        foreach ($websites as $website) {
            $command->info('Breaking iris cache: '.$website->slug.' ('.$website->domain.')');
            $this->handle($website, $command);
        }

        return 0;
    }
}
