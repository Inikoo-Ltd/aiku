<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Models\Web\Banner;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetBanner
{
    use AsObject;

    public function handle(array $webBlock): array
    {

        $fieldValue = Arr::get($webBlock, 'web_block.layout.data.fieldValue', []);
        $bannerId   = Arr::get($fieldValue, 'banner_id');

        if ($banner = Banner::find($bannerId)) {
            $fieldValue['compiled_layout'] = $banner->compiled_layout;
            data_set($webBlock, 'web_block.layout.data.fieldValue', $fieldValue);
        }

        return $webBlock;
    }

}
