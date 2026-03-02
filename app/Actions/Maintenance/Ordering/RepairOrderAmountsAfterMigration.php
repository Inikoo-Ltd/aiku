<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOrderAmountsAfterMigration
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order): void
    {
        foreach ($order->transactions as $transaction) {
            if ($transaction->model_type != 'Product') {
                continue;
            }

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();
            if ($invoiceTransaction) {
                $invoiceTransaction->update([
                    'gross_amount'   => $transaction->gross_amount,
                    'net_amount'     => $transaction->net_amount,
                    'grp_net_amount' => $transaction->grp_net_amount,
                    'org_net_amount' => $transaction->org_net_amount,
                ]);
            }
        }
        $invoice=$order->invoices()->first();
        CalculateInvoiceTotals::run($invoice);
    }


    public string $commandSignature = 'orders:repair_order_amounts {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();


        $this->handle($order);
    }

}
