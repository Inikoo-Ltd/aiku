<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Oct 2025 09:48:34 Central Indonesia Time, Canggu, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Iris\CaptureTrafficSource;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use Illuminate\Support\Facades\DB;

trait HasIrisUserData
{
    /**
     * Build the Iris user data payload shared across Iris/Retina actions.
     *
     * Note: Consuming classes are expected to provide $this->webUser, $this->customer and $this->shop.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getIrisUserData(): array
    {
        $webUser = $this->webUser;

        $cartCount = 0;
        $cartAmount = 0;
        $cardItemsAmountAfterDiscount = 0;
        $customerSalesChannels = [];
        $offerMeters = null;

        if ($this->shop->type == ShopTypeEnum::B2B) {
            $orderInBasket = $this->customer->orderInBasket;
            if ($orderInBasket) {
                $cartCount = $orderInBasket->number_item_transactions;
                $cartAmount = $orderInBasket->total_amount;
                $cardItemsAmountAfterDiscount = $orderInBasket->goods_amount;
                $offerMeters = $orderInBasket->offer_meters;
            }
        }

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
                    'customer_sales_channel_id' => $channel->id,
                    'customer_sales_channel_name' => $channel->customer_sales_channel_name,
                    'platform_id' => $channel->platform_id,
                    'platform_slug' => $channel->slug,
                    'platform_code' => $channel->code,
                    'platform_name' => $channel->name,
                ];
            }
        }

        return [
            'is_logged_in' => true,
            'auth' => [
                'user' => LoggedWebUserResource::make($webUser)->getArray(),
                'customerSalesChannels' => $customerSalesChannels,
            ],
            'customer' => $this->customer,
            'variables' => [
                'reference' => $this->customer->reference,
                'name' => $this->webUser->contact_name,
                'username' => $this->webUser->username,
                'email' => $this->webUser->email,
                'favourites_count' => $this->customer->stats->number_favourites,
                'back_in_stock_count' => $this->customer->backInStockReminder->count(),
                'cart_count' => $cartCount, // products in the basket count
                'cart_amount' => $cartAmount, // order total amount (including shipping, tax, etc.)
                'cart_products_amount' => $cardItemsAmountAfterDiscount,  // order total items amount after discount
            ],
            // 'traffic_source_cookies' => CaptureTrafficSource::run(),
            'offer_meters' => $offerMeters,

        ];
    }
}
