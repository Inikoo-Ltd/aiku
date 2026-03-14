<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveWebsitesSitemap
{
    use AsAction;

    public int $jobTries = 1;

    public function handle(?Command $command = null): void
    {
        /** @var Website $website */
        foreach (Website::where('state', WebsiteStateEnum::LIVE)->where('migrated', true)->get() as $website) {

            if ($command) {
                $command->info("Creating Sitemap for website $website->domain.");
                SaveWebsiteSitemap::run($website, $command);
            } else {
                SaveWebsiteSitemap::dispatch($website);
            }

        }
    }

    public string $commandSignature = 'sitemaps:create';

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
