<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:36:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Retina\UI\GetRetinaFirstLoadProps;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use App\Http\Resources\Helpers\CurrencyResource;
use Illuminate\Support\Facades\DB;
use Tighten\Ziggy\Ziggy;

class HandleRetinaInertiaRequests extends Middleware
{
    use WithIrisInertia;

    protected $rootView = 'app-retina';


    public function share(Request $request): array
    {
        $routeName = $request->route()->getName();
        if (str_starts_with($routeName, 'grp.retina.')) {
            return [];
        }

        /** @var WebUser $webUser */
        $webUser            = $request->user();
        $firstLoadOnlyProps = [];

        if (!$request->inertia() || Session::get('reloadLayout')) {
            $firstLoadOnlyProps          = GetRetinaFirstLoadProps::run($request, $webUser);
            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy('retina'))->toArray(), [
                    'location' => $request->url(),
                ]);
            };
        }

        $website                           = $request->get('website');
        $firstLoadOnlyProps['environment'] = app()->environment();


        $customerSalesChannels = [];
        if ($webUser) {
            $channels = DB::table('customer_sales_channels')
                ->leftJoin('platforms', 'customer_sales_channels.platform_id', '=', 'platforms.id')
                ->select('customer_sales_channels.id', 'customer_sales_channels.name as customer_sales_channel_name', 'platform_id', 'platforms.slug', 'platforms.code', 'platforms.name')
                ->where('customer_id', $webUser->customer_id)
                ->get();

            foreach ($channels as $channel) {
                $customerSalesChannels[$channel->id] = [
                    'customer_sales_channel_id' => $channel->id,
                    'customer_sales_channel_name' => $channel->customer_sales_channel_name,
                    'platform_id'               => $channel->platform_id,
                    'platform_slug'             => $channel->slug,
                    'platform_code'             => $channel->code,
                    'platform_name'             => $channel->name,
                ];
            }
        }
        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'     => [
                    'user'          => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'webUser_count' => $webUser?->customer?->webUsers?->count() ?? 1,
                    'customerSalesChannels' => $customerSalesChannels
                ],
                'currency' => [
                    'code'   => $website->shop->currency->code,
                    'symbol' => $website->shop->currency->symbol,
                    'name'   => $website->shop->currency->name,
                ],
                'flash'    => [
                    'notification'  => fn () => $request->session()->get('notification'),
                    'modal'         => fn () => $request->session()->get('modal'),
                    'gtm'           => fn () => $request->session()->get('gtm'),
                    'confetti'      => fn () => $request->session()->get('confetti')
                ],
                'ziggy'    => [
                    'location' => $request->url(),
                ],
                "retina"   => [
                    "type"     => $website->shop->type->value,
                    "currency" => CurrencyResource::make($website->shop->currency)->toArray(request()),
                    "balance"  => $webUser?->customer?->balance,
                ],
                'iris'     => $this->getIrisData($website, $webUser)
            ],
            parent::share($request),
        );
    }
}
