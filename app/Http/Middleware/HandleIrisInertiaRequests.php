<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    use WithIrisInertia;

    protected $rootView = 'app-iris';


    public function share(Request $request): array
    {
        /** @var WebUser $webUser */
        $webUser = Auth::guard('retina')->user();

        $website = $request->get('website');

        $firstLoadOnlyProps['environment'] = app()->environment();
        $firstLoadOnlyProps['ziggy']       = function () use ($request) {
            return array_merge((new Ziggy('iris'))->toArray(), [
                'location' => $request->url()
            ]);
        };


        $websiteTheme = Arr::get($website->published_layout, 'theme');

        $customerSalesChannels = [];
        if ($webUser && $request->get('shop_type') == ShopTypeEnum::DROPSHIPPING->value) {
            $channels = DB::table('customer_sales_channels')
                ->leftJoin('platforms', 'customer_sales_channels.platform_id', '=', 'platforms.id')
                ->select('customer_sales_channels.id', 'customer_sales_channels.name as customer_sales_channel_name', 'platform_id', 'platforms.slug', 'platforms.code', 'platforms.name')
                ->where('customer_id', $webUser->customer_id)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN->value)
                ->whereNull('deleted_at')
                ->get();

            foreach ($channels as $channel) {
                $customerSalesChannels[$channel->id] = [
                    'customer_sales_channel_id'   => $channel->id,
                    'customer_sales_channel_name' => $channel->customer_sales_channel_name,
                    'platform_id'                 => $channel->platform_id,
                    'platform_slug'               => $channel->slug,
                    'platform_code'               => $channel->code,
                    'platform_name'               => $channel->name,
                ];
            }
        }


        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'     => [
                    'user'                  => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'webUser_count'         => $webUser?->customer?->webUsers?->count() ?? 1,
                    'customerSalesChannels' => $customerSalesChannels
                ],
                'currency' => $request->get('currency_data'),
                'flash'    => [
                    'notification' => fn () => $request->session()->get('notification'),
                    'modal'        => fn () => $request->session()->get('modal')
                ],
                'ziggy'    => [
                    'location' => $request->url(),
                ],
                "retina"   => [
                    "type" => $request->get('shop_type'),
                ],
                "layout"   => [
                    "app_theme" => Arr::get($websiteTheme, 'color'),
                ],
                'iris'     => $this->getIrisData($website, $webUser),


            ],
            parent::share($request),
        );
    }
}
