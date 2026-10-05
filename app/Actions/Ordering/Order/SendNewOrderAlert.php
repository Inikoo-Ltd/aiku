<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderAlertTypeEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Events\BroadcastNewOrderAlert;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;
use Throwable;

class SendNewOrderAlert
{
    use AsObject;

    public const array STAFF_ENTERED_SALES_CHANNELS = [
        SalesChannelTypeEnum::PHONE,
        SalesChannelTypeEnum::SHOWROOM,
        SalesChannelTypeEnum::EMAIL,
        SalesChannelTypeEnum::OTHER,
    ];

    public function handle(Order $order): void
    {
        try {
            $this->broadcast($order);
        } catch (Throwable $e) {
            Sentry::captureException($e);
        }
    }

    private function broadcast(Order $order): void
    {
        if (in_array($order->salesChannel?->type, self::STAFF_ENTERED_SALES_CHANNELS, true)
            || $order->salesChannel?->code === 'intercompany'
            || $order->preOrder()->whereNotNull('parent_order_id')->exists()) {
            return;
        }

        $types = $this->alertTypes($order);

        if ($types === []) {
            return;
        }

        $shop = $order->shop;

        BroadcastNewOrderAlert::dispatch($shop->id, [
            'order_id'  => $order->id,
            'types'     => array_map(fn (OrderAlertTypeEnum $type) => $type->value, $types),
            'reference' => (string) $order->reference,
            'shop'      => $shop->code,
            'customer'  => (string) ($order->customerClient?->name ?: $order->customer?->name),
            'amount'    => (float) $order->total_amount,
            'currency'  => $order->currency->code,
            'is_unpaid' => in_array(OrderAlertTypeEnum::DROPSHIPPING_UNPAID, $types, true),
            'url'       => route('grp.org.shops.show.ordering.orders.show', [$order->organisation->slug, $shop->slug, $order->slug]),
        ]);
    }

    /**
     * @return array<int, OrderAlertTypeEnum>
     */
    public function alertTypes(Order $order): array
    {
        return match ($order->shop->type) {
            ShopTypeEnum::B2B, ShopTypeEnum::B2C => [$this->ecomSize($order)],
            ShopTypeEnum::DROPSHIPPING => $this->dropshippingTypes($order),
            default => [],
        };
    }

    public function ecomSize(Order $order): OrderAlertTypeEnum
    {
        $sizes     = Arr::get($order->shop->settings, 'order_alerts', []);
        $netAmount = (float) $order->net_amount;

        if (isset($sizes['big_above']) && $netAmount > $sizes['big_above']) {
            return OrderAlertTypeEnum::ECOM_BIG;
        }

        if (isset($sizes['small_below']) && $netAmount < $sizes['small_below']) {
            return OrderAlertTypeEnum::ECOM_SMALL;
        }

        return OrderAlertTypeEnum::ECOM_NORMAL;
    }

    /**
     * @return array<int, OrderAlertTypeEnum>
     */
    public function dropshippingTypes(Order $order): array
    {
        $types = [];

        if ($order->isPlacedOnAChannel() && $this->isFirstOrderOfItsChannel($order)) {
            $types[] = OrderAlertTypeEnum::DROPSHIPPING_FIRST_CHANNEL_ORDER;
        }

        if ($this->isUnpaid($order)) {
            $types[] = OrderAlertTypeEnum::DROPSHIPPING_UNPAID;
        }

        return $types;
    }

    public function isUnpaid(Order $order): bool
    {
        return $order->pay_status !== OrderPayStatusEnum::PAID
            && $order->to_be_paid_by !== OrderToBePaidByEnum::CASH_ON_DELIVERY;
    }

    private function isFirstOrderOfItsChannel(Order $order): bool
    {
        return $order->customer_sales_channel_id !== null
            && Order::where('customer_sales_channel_id', $order->customer_sales_channel_id)
                ->where('id', '!=', $order->id)
                ->where('state', '!=', OrderStateEnum::CREATING)
                ->doesntExist();
    }
}
