<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Fulfilment\FulfilmentResource;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Arr;

class GetRetinaLayout
{
    use AsAction;

    public function handle($request, ?WebUser $webUser): array
    {
        /** @var Website $website */
        $website = $request->get('website');
        if (!$webUser) {
            return [
                'app_theme' => Arr::get($website->published_layout, 'theme.color', []),
                'website'   => GroupResource::make($request->get('website'))->getArray(),
            ];
        }

        $isPreRegistration = false;
        if ($webUser) {
            $customer = $webUser->customer;
            if ($customer && $customer->status === CustomerStatusEnum::PRE_REGISTRATION) {
                $isPreRegistration = true;
            }
        }

        $additionalData = [];
        if ($fulfilment = $website->shop?->fulfilment) {
            $additionalData = [
                'fulfilment' => FulfilmentResource::make($fulfilment)
            ];
        }

        return [
            ...$additionalData,
            'website'   => GroupResource::make($request->get('website'))->getArray(),
            'customer'  => CustomersResource::make($webUser->customer)->getArray(),
            'app_theme' => Arr::get($website->published_layout, 'theme.color', []),

            'navigation' => $isPreRegistration ? null : match ($request->get('website')->type->value) {
                'fulfilment' => GetRetinaFulfilmentNavigation::run($webUser),
                'dropshipping' => GetRetinaDropshippingNavigation::run($webUser),
                'b2b' => GetRetinaB2bNavigation::run($webUser),
                default => []
            },
        ];
    }
}
