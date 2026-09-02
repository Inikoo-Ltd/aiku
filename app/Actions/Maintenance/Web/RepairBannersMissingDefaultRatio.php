<?php

/*
 * author Louis Perez
 * created on 27-02-2026-10h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Banner\UpdateBanner;
use App\Models\Web\Banner;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairBannersMissingDefaultRatio
{
    use WithActionUpdate;


    protected function handle(Banner $banner): void
    {
        UpdateBanner::make()->action($banner, ['ratio' => $banner->type->defaultRatio()]);
    }

    public string $commandSignature = 'banners:repair_missing_default_ratio';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $query     = Banner::where(function ($q) {
            $q->whereNull('ratio')
            ->orWhereRaw("TRIM(ratio) = ''");
        })->get();

        foreach ($query as $banner) {
            $command->info('Fixing '.$banner->name." ratio");
            $this->handle($banner);
        }
    }

}
