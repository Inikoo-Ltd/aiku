<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteItemHydrateSalesType implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(DeliveryNoteItem $deliveryNoteItem): string
    {
        return $deliveryNoteItem->id;
    }


    public function handle(DeliveryNoteItem $deliveryNoteItem): void
    {
        $salesType = DeliveryNoteItemSalesTypeEnum::NA;

        $deliveryNote = $deliveryNoteItem->deliveryNote;
        if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
            $transaction = $deliveryNoteItem->transaction;
            if ($transaction && $transaction->order) {
                $salesType = $this->getSalesTypeFromOrder($transaction->order);
            }
        }

        $deliveryNoteItem->update(
            [
                'sales_type' => $salesType,
            ]
        );
    }

    public function getSalesTypeFromOrder(Order $order): ?DeliveryNoteItemSalesTypeEnum
    {
        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            return DeliveryNoteItemSalesTypeEnum::DROPSHIPPING;
        } else {
            return $this->getSalesTypeFromNonDropshippingShop($order);
        }
    }

    private function getSalesTypeFromNonDropshippingShop($order): ?DeliveryNoteItemSalesTypeEnum
    {

        $salesType = DeliveryNoteItemSalesTypeEnum::B2B;

        if ($order->is_vip) {
            $salesType = DeliveryNoteItemSalesTypeEnum::VIP;
        }
        if ($order->as_employee_id) {
            $salesType = DeliveryNoteItemSalesTypeEnum::PARTNER;
        }
        if ($order->as_organisation_id) {
            $salesType = DeliveryNoteItemSalesTypeEnum::PARTNER;
        }

        $salesChannel = $order->salesChannel;
        if ($salesChannel && $salesChannel->type == SalesChannelTypeEnum::MARKETPLACE) {
            $salesType = DeliveryNoteItemSalesTypeEnum::MARKETPLACE;
        }

        return $salesType;
    }

}
