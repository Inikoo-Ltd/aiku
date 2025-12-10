<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 14:03:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Accounting\Invoice\UpdateInvoicePaymentState;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairWooOrdersTaxCategory
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function fixOrder(Order $order, Command $command): void
    {
        $taxCategory = GetTaxCategory::run(
            country: $order->organisation->country,
            taxNumber: $order->customer->taxNumber,
            billingAddress: $order->billingAddress,
            deliveryAddress: $order->deliveryAddress,
            isRe: false,
        );

        if ($taxCategory->id == $order->tax_category_id) {
            return;
        }

        $command->info('Order '.$order->slug.'  has tax category '.$order->tax_category_id.' and will be updated to '.$taxCategory->id);

        $dataToUpdate = [
            'tax_category_id' => $taxCategory->id,
            'tax_amount'      => $taxCategory->rate * $order->net_amount,
            'total_amount'    => $order->net_amount + $taxCategory->rate * $order->net_amount
        ];

        $order->update($dataToUpdate);

        $order->transactions()->update(['tax_category_id' => $taxCategory->id]);

        UpdateOrderPaymentsStatus::run($order);
    }

    public function fixInvoice(Invoice $invoice, Command $command): void
    {
        $order = $invoice->order;


        if ($invoice->tax_category_id == $order->tax_category_id) {
            return;
        }

        $command->info('Invoice '.$invoice->slug.'  has tax category '.$invoice->tax_category_id.' and will be updated to '.$order->tax_category_id);

        $dataToUpdate = [
            'tax_category_id' => $order->tax_category_id,
            'tax_amount'      => $order->taxCategory->rate * $invoice->net_amount,
            'total_amount'    => $invoice->net_amount + $order->taxCategory->rate * $invoice->net_amount,
            'effective_total' => $invoice->net_amount + $order->taxCategory->rate * $invoice->net_amount,
        ];


        $invoice->update($dataToUpdate);

        $invoice->invoiceTransactions()->update(['tax_category_id' => $order->tax_category_id]);

        UpdateInvoicePaymentState::run($invoice);
    }


    public string $commandSignature = 'repair:woo_orders_tax_category';

    public function asCommand(Command $command): void
    {
        Order::where('platform_id', 3)->where('shop_id', 13)->where('tax_category_id', 1)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($command) {
                foreach ($models as $model) {
                    $this->fixOrder($model, $command);
                }
            });

        Invoice::where('platform_id', 3)->where('shop_id', 13)->where('tax_category_id', 1)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($command) {
                foreach ($models as $model) {
                    $this->fixInvoice($model, $command);
                }
            });
    }

}
