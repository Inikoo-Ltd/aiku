<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class SetWebsiteShortImageUrls
{
    use AsAction;

    public string $commandSignature = 'website:short_image_urls {website : The website slug} {state : on or off}';

    public function handle(Website $website, bool $enabled, ?Command $command = null): void
    {
        $settings                     = $website->settings;
        $settings['short_image_urls'] = $enabled;
        $website->update(['settings' => $settings]);

        ClearCacheByWildcard::run(config('iris.cache.webpage.prefix').'_'.$website->id.'_*', $command);
        BreakWebsiteIrisCache::run($website, $command);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if (!in_array($command->argument('state'), ['on', 'off'])) {
            $command->error('State must be on or off');

            return 1;
        }

        $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        $this->handle($website, $command->argument('state') === 'on', $command);
        $command->info("Short image urls {$command->argument('state')} for $website->domain");

        return 0;
    }
}
