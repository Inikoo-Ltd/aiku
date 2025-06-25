<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaDropshippingHomeData
{
    use AsObject;

    public function handle(Customer $customer): array
    {
        $customerChannels = $customer->customerSalesChannels()->with('platform:id,type')->get();
        $totalPlatforms = $customerChannels->count();

        $metas = [];

        foreach (PlatformTypeEnum::cases() as $platformType) {
            $platformTypeName = $platformType->value;

            $platform = $customerChannels->filter(function ($channel) use ($platformTypeName) {
                return $channel->platform->type->value === $platformTypeName;
            });

            $metas[] = [
                'tooltip' => __($platformType->labels()[$platformTypeName]),
                'icon'    => [
                    'tooltip' => $platform->count() > 0 ? 'active' : 'inactive',
                    'icon'    => $platform->count() > 0 ? 'fas fa-check-circle' : 'fas fa-times-circle',
                    'class'   => $platform->count() > 0 ? 'text-green-500' : 'text-red-500'
                ],
                'count'   => $platform->count(),
                // 'route' => [
                //     'name'       => 'grp.org.shops.show.catalogue.departments.index',
                //     'parameters' => [
                //         'organisation' => $shop->organisation->slug,
                //         'shop'         => $shop->slug,
                //         'index_elements[state]' => $platformTypeName
                //     ]
                // ],
            ];

        }

        return [
            'customer' => CustomerResource::make($customer)->getArray(),
            'channels' => CustomerSalesChannelsResource::collection($customerChannels),
            'stats'       => [
                    [
                        'label' => __('Channels'),
                        'route' => [
                            'name'       => 'retina.dropshipping.customer_sales_channels.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-folder-tree',
                        "color" => "",
                        'value' => $totalPlatforms,


                        'metas' => $metas
                    ],
            ]
        ];
    }
}
