<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Banner\Search;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Web\Banner;

class ReindexBannerSearch
{
    use WithHydrateCommand;

    public string $commandSignature = 'search:banners {organisations?*} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = Banner::class;
    }

    public function handle(Banner $banner): void
    {
        BannerRecordSearch::run($banner);
    }
}
