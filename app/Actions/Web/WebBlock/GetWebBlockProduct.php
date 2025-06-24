<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:07:56 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Web\WebBlockProductResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProduct
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $permissions =  [];
        $channelIds = [];

        if (request()->user()) {
            $channelIds = $webpage->model
                ->portfolios()
                ->where('customer_id', request()->user()->customer_id)
                ->select('customer_sales_channel_id')
                ->distinct()
                ->pluck('customer_sales_channel_id')
                ->toArray();
        }

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['product']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', WebBlockProductResource::make($webpage->model)->toArray(request()));
        data_set($webBlock, 'web_block.layout.data.fieldValue.productChannels', $channelIds);

        return $webBlock;
    }

}
