<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Oct 2023 13:27:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner;

use App\Models\Portfolio\Banner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

trait HasBannerCommand
{
    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    protected function getBanner($command): ?Banner
    {
        $bannerData = DB::table('banners')->select('id', 'customer_id')->where('slug', $command->argument('slug'))->first();
        if (! $bannerData) {
            $command->error('Banner not found');

            return null;
        }

        Config::set('global.customer_id', $bannerData->customer_id);

        return Banner::find($bannerData->id);
    }
}
