<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Fulfilment\FulfilmentResource;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Arr;

class GetRetinaLayout
{
    use AsAction;

    public function handle($request, ?WebUser $webUser): array
    {
        /** @var \App\Models\Web\Website $website */
        $website    = $request->get('website');
        if (!$webUser) {
            return [
                'app_theme' => Arr::get($website->published_layout, 'theme.color', []),
                'website'  => GroupResource::make($request->get('website'))->getArray(),
            ];
        }

        $additionalData = [];
        if ($fulfilment = $website->shop?->fulfilment) {
            $additionalData = [
                'fulfilment' => FulfilmentResource::make($fulfilment)
            ];
        }

        $layout =  [
            ...$additionalData,
            'website'  => GroupResource::make($request->get('website'))->getArray(),
            'customer' => CustomersResource::make($webUser->customer)->getArray(),
            'app_theme' => Arr::get($website->published_layout, 'theme.color', []),

            'navigation' => match ($request->get('website')->type->value) {
                'fulfilment' => GetRetinaFulfilmentNavigation::run($webUser),
                'dropshipping' => GetRetinaDropshippingNavigation::run($webUser),
                'b2b' => GetRetinaB2bNavigation::run($webUser),
                default      => []
            },
        ];

        if ($webUser->shop->type->value === 'b2b' || $webUser->shop->type->value === 'dropshipping') {
            $layout['web_page'] = [
                'header'        => Arr::get($website->published_layout, 'header', []),
                'menu'          => Arr::get($website->published_layout, 'menu', []),
                'footer'        => Arr::get($website->published_layout, 'footer', [])
            ];
        }

        return $layout;
    }
}
