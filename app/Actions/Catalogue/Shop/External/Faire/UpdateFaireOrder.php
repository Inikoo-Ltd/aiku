<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Mar 2026 22:17:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateFaireOrder extends OrgAction
{
    use HasDeliveryNoteHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): void
    {
        $shop = $order->shop;

        $orderFaireData               = $shop->getFaireOrder($order->external_id);
        $transactionCommissionsFactor = Arr::get($orderFaireData, 'payout_costs.commission_bps', 0) / 10000;


        foreach ($orderFaireData['items'] as $item) {
            $transaction = Transaction::where('order_id', $order->id)->where('marketplace_id', $item['id'])->first();
            if (!$transaction) {
                $transactionData = $this->addTransaction($item, $order->shop, $transactionCommissionsFactor);
                if ($transactionData['state'] == 'ok') {
                    $transactionData = $transactionData['data'];
                    $transaction     = StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $transactionData['historical_asset'],
                        modelData: Arr::except($transactionData, 'historical_asset')
                    );

                    /** @var \App\Models\Dispatching\DeliveryNote $deliveryNote */
                    $deliveryNote = $order->deliveryNotes()->whereNotIn('state', [
                        DeliveryNoteStateEnum::CANCELLED,
                        DeliveryNoteStateEnum::DISPATCHED,
                        DeliveryNoteStateEnum::FINALISED,
                    ])->where('type', DeliveryNoteTypeEnum::ORDER)->first();
                    if ($deliveryNote) {
                        $transactionData = [
                            'state'           => TransactionStateEnum::IN_WAREHOUSE,
                            'status'          => TransactionStatusEnum::PROCESSING,
                            'in_warehouse_at' => now()
                        ];
                        if ($transaction->in_warehouse_at == null) {
                            data_set($transactionData, 'in_warehouse_at', now());
                        }
                        $transaction->update($transactionData);

                        $product = Product::find($transaction->model_id);


                        foreach ($product->orgStocks as $orgStock) {
                            $quantity             = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
                            $deliveryNoteItemData = [
                                'org_stock_id'               => $orgStock->id,
                                'transaction_id'             => $transaction->id,
                                'quantity_required'          => $quantity,
                                'original_quantity_required' => $quantity,
                            ];

                            StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteItemData);
                        }

                        $oldState = $deliveryNote->state;

                        if (in_array($deliveryNote->state, [
                            DeliveryNoteStateEnum::PICKED,
                            DeliveryNoteStateEnum::PACKING,
                            DeliveryNoteStateEnum::PACKED,
                        ])) {
                            $deliveryNote->update([
                                'state' => DeliveryNoteStateEnum::HANDLING,
                            ]);

                            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
                            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::HANDLING);

                        }



                    }
                }

                continue;
            }

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
            $invoiceTransaction?->update([
                'historic_asset_id' => $product->current_historic_asset_id,
                'gross_amount'      => $netAmount,
                'net_amount'        => $netAmount,
                'grp_net_amount'    => $netAmount * $grpExchange,
                'org_net_amount'    => $netAmount * $orgExchange,
            ]);
        }


        $amountOff = Arr::get($orderFaireData, 'payout_costs.total_brand_discounts.amount_minor', 0) / 100;

        $order->update([
            'amount_off' => $amountOff,
        ]);

        $tax = 0;

        foreach (Arr::get($orderFaireData, 'payout_costs.taxes', []) as $taxData) {
            $tax += Arr::get($taxData, 'value.amount_minor', 0) / 100;
        }

        if ($tax == 0) {
            if (IsEuropeanUnion::run($shop->organisation->country->code)) {
                $taxCategory = TaxCategory::where('status', true)->where('type', 'eu_vtc')->first();
            } else {
                $taxCategory = TaxCategory::where('status', true)->where('type', 'outside')->first();
            }
        } else {
            $taxCategory = GetTaxCategory::run(
                country: $order->organisation->country,
                taxNumber: null,
                billingAddress: $order->billingAddress,
                deliveryAddress: $order->deliveryAddress,
            );
        }

        $order = UpdateOrder::run($order, [
            'tax_category_id' => $taxCategory->id,
        ]);


        CalculateOrderTotalAmounts::run($order);
        $invoice = $order->invoices()->first();
        if ($invoice) {
            $order->update([
                'amount_off' => $amountOff,
            ]);


            CalculateInvoiceTotals::run($invoice);
        }
    }

    public string $commandSignature = 'faire:update_order {order?}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('order')) {
            $order = Order::where('slug', $command->argument('order'))->firstOrFail();
            $this->handle($order);

            return 0;
        }

        $faireShops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)->pluck('id')->toArray();

        /** @var Order $order */
        foreach (Order::whereIn('shop_id', $faireShops)->get() as $order) {
            $command->info("Updating order {$order->shop->slug} $order->slug");
            $this->handle($order);
        }

        return 0;
    }

    public function addTransaction($item, $shop, $transactionCommissionsFactor): array
    {
        return GetFaireOrdersInShop::make()->processFaireOrderItem($item, $shop, $transactionCommissionsFactor);
    }


    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($order->organisation, $request);

        $this->handle($order);
    }
}
