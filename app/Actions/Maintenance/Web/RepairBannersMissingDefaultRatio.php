<?php

/*
 * author Louis Perez
 * created on 27-02-2026-10h-00m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Banner;
use Illuminate\Console\Command;

class RepairBannersMissingDefaultRatio
{
    use WithActionUpdate;


    protected function handle(Banner $banner): void
    {
        $banner->touch();
    }

    public string $commandSignature = 'banners:repair_missing_default_ratio';

    public function asCommand(Command $command): void
    {
        $query     = Banner::all();
        foreach ($query as $banner) {
            $command->info('Fixing '.$banner->name." ratio");
            $this->handle($banner);
        }
    }

}
