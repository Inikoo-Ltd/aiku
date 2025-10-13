<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Oct 2025 13:30:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisAuthData extends RetinaAction
{
    public function handle(): array
    {
        if (!$this->webUser) {
            return [
                'is_logged_in' => false,
            ];
        }

        return $this->getIrisUserData();
    }


    public function getIrisUserData(): array
    {

        $webUser= $this->webUser;

        $cartCount  = 0;
        $cartAmount = 0;

        if ($this->shop->type == ShopTypeEnum::B2B) {
            $orderInBasket = $this->customer->orderInBasket;
            $cartCount     = $orderInBasket ? $orderInBasket->number_item_transactions : 0;
            $cartAmount    = $orderInBasket ? $orderInBasket->total_amount : 0;
        }



        $customerSalesChannels = [];
        if ($webUser && request()->get('shop_type') == ShopTypeEnum::DROPSHIPPING->value) {
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

        return [

            'is_logged_in' => true,

            'auth' => [
                'user'                  => LoggedWebUserResource::make($webUser)->getArray(),
                'webUser_count'         => $webUser->customer->webUsers->count() ?? 1,
                'customerSalesChannels' => $customerSalesChannels
            ],


            'customer'     => $this->customer,
            'variables'    => [
                'reference'        => $this->customer->reference,
                'name'             => $this->webUser->contact_name,
                'username'         => $this->webUser->username,
                'email'            => $this->webUser->email,
                'favourites_count' => $this->customer->stats->number_favourites,
                //'items_count'      => $itemsCount,  // TODO remove this
                'cart_count'       => $cartCount,  // Count of unique items
                'cart_amount'      => $cartAmount,
            ],
        ];
    }

    public function asController(Collection $collection, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation($request);

        return $this->handle();
    }


}
