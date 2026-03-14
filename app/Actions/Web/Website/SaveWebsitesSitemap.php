<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;

class SaveWebsitesSitemap extends OrgAction
{
    public function handle(?Command $command = null): void
    {
        /** @var Website $website */
        foreach (Website::where('state', WebsiteStateEnum::LIVE)->where('migrated', true)->get() as $website) {
            $command?->info("Creating Sitemap for website $website->domain.");
            SaveWebsiteSitemap::run($website, $command);
        }
    }

    public string $commandSignature = 'sitemaps:create';

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
