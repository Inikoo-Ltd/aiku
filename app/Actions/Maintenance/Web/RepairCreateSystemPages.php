<?php

/*
 * Author Louis Perez
 * Created on 03-09-2026-14h-57m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairCreateSystemPages
{
    use WithActionUpdate;
    use WithRepairWebpages;

    protected function handle(Website $website, ?Command $command = null): void
    {
        foreach (WebpageSubTypeEnum::systemPages() as $subTypeValue => $systemPage) {
            $subType = WebpageSubTypeEnum::from($subTypeValue);

            $webpage = Webpage::where('website_id', $website->id)
                ->where('type', WebpageTypeEnum::SYSTEM_PAGE)
                ->where('sub_type', $subType)
                ->first();

            if (!$webpage) {
                $webpage = StoreWebpage::make()->action($website, [
                    'url'      => $systemPage['url'],
                    'code'     => $systemPage['url'],
                    'title'    => $systemPage['title'],
                    'type'     => WebpageTypeEnum::SYSTEM_PAGE,
                    'sub_type' => $subType,
                ]);
            }

            $website->update([$systemPage['website_field'] => $webpage->id]);

            $command?->info("{$systemPage['title']}: {$webpage->canonical_url}");
        }
    }

    public string $commandSignature = 'repair:create_system_pages {--website_id=}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $websites = Website::where('status', true)
            ->when(
                $command->option('website_id'),
                fn ($q) => $q->where('id', $command->option('website_id'))
            )
            ->get();

        foreach ($websites as $website) {
            $command->info("-- Processing: {$website->slug}");
            $this->handle($website, $command);
        }
    }
}
