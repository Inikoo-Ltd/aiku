<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;

class RepairCreateLandingPage
{
    use WithActionUpdate;
    use WithRepairWebpages;

    protected function handle(Website $website, Command $command): void
    {
        $landingPage = StoreWebpage::make()->action($website, [
            'url'           => 'landing-page',
            'code'          => 'landing-page',
            'title'         => 'Landing Page',
            'type'          => WebpageTypeEnum::LANDING_PAGE,
            'sub_type'      => WebpageSubTypeEnum::LANDING_PAGE,
        ]);

        $website->update([
            'landing_page_id'   => $landingPage->id
        ]);

        $command->info("Landing Page created: {$landingPage->canonical_url}");
    }

    public string $commandSignature = 'repair:create_landing_page {--website_id=}';

    public function asCommand(Command $command): void
    {
        $websites = Website::where('status', true)
            ->when($command->option('website_id'), 
                fn ($q) => $q->where('id', $command->option('website_id'))
            )
            ->get();

        foreach ($websites as $website) {
            $command->info("-- Processing: {$website->slug}");
            $this->handle($website, $command);
        }
    }
}
