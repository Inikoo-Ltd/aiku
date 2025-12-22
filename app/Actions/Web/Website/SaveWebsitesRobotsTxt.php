<?php

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use App\Enums\Web\Webpage\WebpageStateEnum;

class SaveWebsitesRobotsTxt extends OrgAction
{
    public function handle(?Command $command = null): void
    {
        /** @var Website $website */
        foreach (Website::where('state', WebpageStateEnum::LIVE)->get() as $website) {
            SaveWebsiteRobotsTxt::run($website);

            $command?->info(
                "Robots.txt for website {$website->domain} has been saved."
            );
        }
    }

    public string $commandSignature = 'robots:create';

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
