<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\OrgAction;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UpdateFaireOrder extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order, Command|null $command = null): void
    {
        $shop = $order->shop;

        $orderFaireData = $shop->getFaireOrder($order->external_id);

        foreach ($orderFaireData['items'] as $item) {
            $transaction = Transaction::where('order_id', $order->id)->where('marketplace_id', $item['id'])->first();

            /** @var Product $product */
            $product = $transaction->model;


            $product = UpdateProduct::run(
                $product,
                [
                    'price' => $product->units * Arr::get($item, 'price.amount_minor') / 100,
                ]
            );

            $currencyCode = Arr::get($item, 'price.currency');
            $currency     = Currency::where('code', $currencyCode)->first();
            $exchange     = GetCurrencyExchange::run($currency, $shop->currency);



            $netAmount = $exchange * $item['quantity'] * Arr::get($item, 'price.amount_minor') / 100;

            $orgExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->currency);
            $grpExchange = GetCurrencyExchange::run($shop->currency, $shop->group->currency);


            $transaction->update([
                'historic_asset_id' => $product->current_historic_asset_id,
                'gross_amount'      => $netAmount,
                'net_amount'        => $netAmount,
                'grp_net_amount'    => $netAmount * $grpExchange,
                'org_net_amount'    => $netAmount * $orgExchange,
            ]);

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();
            if ($invoiceTransaction) {

                $invoiceTransaction->update([
                    'historic_asset_id' => $product->current_historic_asset_id,
                    'gross_amount'      => $netAmount,
                    'net_amount'        => $netAmount,
                    'grp_net_amount'    => $netAmount * $grpExchange,
                    'org_net_amount'    => $netAmount * $orgExchange,
                ]);

            }






        }
        CalculateOrderTotalAmounts::run($order);
        $invoice = $order->invoices()->first();
        if ($invoice) {
            CalculateInvoiceTotals::run($invoice);
        }

    }


    public string $commandSignature = 'faire:update_order {order}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order, $command);

        return 0;
    }
}
