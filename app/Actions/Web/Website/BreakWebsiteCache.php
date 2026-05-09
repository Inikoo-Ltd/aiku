<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class BreakWebsiteCache extends OrgAction implements ShouldBeUnique
{
    public Website $website;

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    public function handle(Website $website, ?Command $command = null): Website
    {
        ClearCacheByWildcard::run(config('iris.cache.webpage_path.prefix').'_domain:*', $command);
        ClearCacheByWildcard::run(config('iris.cache.webpage_path.prefix').'_'.$website->id.'_*', $command);
        ClearCacheByWildcard::run(config('iris.cache.webpage.prefix').'_'.$website->id.'_*', $command);
        ClearCacheByWildcard::run("irisData:website:$website->id:*", $command);

        BreakWebsiteVarnishCache::run($website);


        return $website;
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function getCommandSignature(): string
    {
        return 'website:break_cache {slug}';
    }

    public function asCommand(Command $command): int
    {
        $this->website = Website::where('slug', $command->argument('slug'))->first();
        $this->handle($this->website, $command);

        return 0;
    }

}
