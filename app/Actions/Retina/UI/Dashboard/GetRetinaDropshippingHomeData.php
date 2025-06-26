<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\UI\Dashboard;

use App\Actions\Utils\Abbreviate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\CRM\CustomerSalesChannelsResource;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Services\QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
                'logo_icon' => $platformType->value,
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

        $webUser = request()->user();


        if ($webUser instanceof WebUser) {
            $webRequest = $webUser->webUserRequests();
            $queryBuilder = QueryBuilder::for($webRequest);
            $queryBuilder->where('route_name', 'like', 'retina.dropshipping.customer_sales_channels.%');

            $latestWebRequests = $queryBuilder->orderBy('date', 'desc')->take(5)->get();
            $latestChannel = [];
            foreach ($latestWebRequests as $latestWebRequest) {
                if (!$latestWebRequest->route_params) {
                    continue;
                }


                foreach (PlatformTypeEnum::cases() as $platformType) {
                    $platform = Str::lower(Abbreviate::run($platformType->value));

                    $params = json_decode($latestWebRequest->route_params, true);
                    $customerSalesChannel = Arr::get($params, 'customerSalesChannel');
                    if ($customerSalesChannel) {
                        if (Str::startsWith($customerSalesChannel, $platform)) {
                            $latestChannel[$customerSalesChannel] = [
                                'route' => route(
                                    $latestWebRequest->route_name,
                                    array_merge(
                                        $params,
                                        ['customerSalesChannel' => $customerSalesChannel]
                                    )
                                ),
                                'slug' => $customerSalesChannel,
                                'date' => $latestWebRequest->date,
                                'platform' => $platformType->value,
                            ];
                            break;
                        }
                    }
                }

            }


            // route, slug, date, nama channel
            $latestChannel = array_values($latestChannel);
        }


        return [
            'customer' => CustomerResource::make($customer)->getArray(),
            'channels' => CustomerSalesChannelsResource::collection($customerChannels)->toArray(request()),
            'stats'       => [
                [
                    'label' => __('Channels'),
                    'route' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.index',
                        'parameters' => []
                    ],
                    'color' => '#E87928',
                    'icon'  => [
                        'icon' => 'fal fa-code-branch',
                        'tooltip' => __('Channels'),
                        'icon_rotation' => '90',
                    ],
                    // "color" => "",
                    'value' => $totalPlatforms,


                    'metas' => $metas
                ],
            ],
            'last_visited_channels' => $latestChannel
        ];
    }
}
