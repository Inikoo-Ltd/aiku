<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\TaxNumber;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When a customer's tax number changes validity, every open order is re-rated - but only
 * until it is invoiced: an issued invoice is immutable and only a person may correct it.
 * External shop orders (Faire and friends) keep the tax their marketplace charged.
 */
class ResetCustomerOrdersTaxCategory
{
    use AsAction;

    public function handle(TaxNumber $taxNumber): void
    {
        $owner = $taxNumber->owner;
        if (!$owner instanceof Customer) {
            return;
        }

        $orders = $owner->orders()
            ->whereNotIn('orders.state', [OrderStateEnum::DISPATCHED, OrderStateEnum::CANCELLED])
            ->whereNull('orders.source_id')
            ->whereDoesntHave('invoices')
            ->whereHas('shop', fn ($query) => $query->whereNot('type', ShopTypeEnum::EXTERNAL))
            ->get();

        foreach ($orders as $order) {
            ResetOrderTaxCategory::run($order);
        }
    }
}
