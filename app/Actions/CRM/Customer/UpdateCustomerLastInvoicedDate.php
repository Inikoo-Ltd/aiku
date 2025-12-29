<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Dec 2025 17:22:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCustomerLastInvoicedDate
{
    use AsAction;

    public function handle(Customer $customer, ?Carbon $date = null): void
    {
        if (!$date) {
            $date = $customer->invoices()
                ->where('invoices.in_process', false)
                ->where('type', InvoiceTypeEnum::INVOICE)
                ->max('date');

            if ($date) {
                $date = Carbon::parse($date);
            }
        }

        $customer->update(['last_invoiced_at' => $date]);
        $customer->stats()->update(['last_invoiced_at' => $date]);
        Cache::put("customer_last_invoiced_at_{$customer->id}", $date, 7 * 24 * 60 * 60);


        if ($customer->wasChanged('last_invoiced_at')) {
            foreach ($customer->orders()->where('state', OrderStateEnum::CREATING)->get() as $order) {
                CalculateOrderTotalAmounts::run($order, true, true, false, true);
            }

        }
    }

}
