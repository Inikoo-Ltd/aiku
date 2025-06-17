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
use App\Models\Web\Website;
use Illuminate\Console\Command;

class SaveWebsitesSitemap extends OrgAction
{
    public function handle(?Command $command = null): void
    {

        /** @var Website $website */
        foreach (Website::where('state', WebpageStateEnum::LIVE)->get() as $website) {
            $numberItems = SaveWebsiteSitemap::run($website);
            $command?->info("Sitemap for website ID $website->domain has been saved. ($numberItems)");
        }

    }

    public string $commandSignature = 'sitemaps:create';

    public function asCommand(Command $command): void
    {

        $this->handle($command);
    }
}
