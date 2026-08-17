<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Mar 2026 22:17:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Accounting\InvoiceTransaction\DeleteInvoiceTransaction;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteQuantitySync;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateFaireOrder extends OrgAction
{
    use WithDeliveryNoteQuantitySync;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): void
    {
        $shop = $order->shop;

        $orderFaireData = $shop->getFaireOrder($order->external_id);

        if (Arr::get($orderFaireData, 'state') == 'CANCELED') {
            foreach ($order->invoices as $invoice) {
                DeleteInvoice::run($invoice, [
                    'deleted_note' => 'Cancelled by Faire'
                ]);
            }
            foreach ($order->deliveryNotes as $deliveryNote) {
                if ($deliveryNote->state != DeliveryNoteStateEnum::CANCELLED) {
                    CancelDeliveryNote::run($deliveryNote, null, false);
                }
            }

            CancelOrder::run($order);


            return;
        }

        $transactionCommissionsFactor = Arr::get($orderFaireData, 'payout_costs.commission_bps', 0) / 10000;
        $orderCommission              = Arr::get($orderFaireData, 'payout_costs.commission.amount_minor', 0) / 100;
        $faireItemIds                 = [];

        foreach ($orderFaireData['items'] as $item) {
            $faireItemIds[] = $item['id'];

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

                    $this->createDeliveryNoteItem($order, $transaction);
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


            $quantity = $item['quantity'] / $product->units;

            $dataToUpdate = [
                'historic_asset_id' => $product->current_historic_asset_id,
                'gross_amount'      => $netAmount,
                'net_amount'        => $netAmount,
                'grp_net_amount'    => $netAmount * $grpExchange,
                'org_net_amount'    => $netAmount * $orgExchange,
                'quantity_ordered'  => $quantity,
            ];


            if ($order->state == OrderStateEnum::DISPATCHED) {
                $dataToUpdate['quantity_dispatched'] = $quantity;
            }


            $transaction->update($dataToUpdate);

            $this->updateDeliveryNoteItem($order, $transaction);

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();

            $invoiceTransaction?->update([
                'historic_asset_id' => $product->current_historic_asset_id,
                'gross_amount'      => $netAmount,
                'net_amount'        => $netAmount,
                'grp_net_amount'    => $netAmount * $grpExchange,
                'org_net_amount'    => $netAmount * $orgExchange,
                'quantity'          => $quantity,
            ]);
        }

        $toDeleteTransactionIds = Transaction::where('order_id', $order->id)
            ->whereNotNull('marketplace_id')
            ->whereNotIn('marketplace_id', $faireItemIds)->pluck('id')->toArray();

        foreach ($toDeleteTransactionIds as $transactionId) {
            $transaction = Transaction::find($transactionId);
            if ($transaction) {
                DeleteTransaction::run($transaction);
            }
        }

        $invoiceIds = $order->invoices()->pluck('id')->toArray();
        if (!empty($invoiceIds)) {
            $toDeleteInvoiceTransactionIds = InvoiceTransaction::whereIn('invoice_id', $invoiceIds)
                ->whereNotNull('marketplace_id')
                ->whereNotIn('marketplace_id', $faireItemIds)->pluck('id')->toArray();

            foreach ($toDeleteInvoiceTransactionIds as $invoiceTransactionId) {
                $invoiceTransaction = InvoiceTransaction::find($invoiceTransactionId);
                if ($invoiceTransaction) {
                    DeleteInvoiceTransaction::run($invoiceTransaction);
                }
            }
        }


        $amountOff = Arr::get($orderFaireData, 'payout_costs.total_brand_discounts.amount_minor', 0) / 100;

        $order->update([
            'amount_off'        => $amountOff,
            'commission_amount' => $orderCommission,
        ]);


        $taxCategory = $this->getCategoryTaxCategory($order, $orderFaireData);

        $order = UpdateOrder::run($order, [
            'tax_category_id' => $taxCategory->id,
        ]);


        CalculateOrderTotalAmounts::run($order);

        $faireTaxAmount = $this->getFaireTaxAmount($orderFaireData, $shop);
        $order->refresh();
        $order->update([
            'tax_amount'   => $faireTaxAmount,
            'total_amount' => $order->net_amount + $faireTaxAmount,
        ]);

        $invoice = $order->invoices()->first();
        if ($invoice) {
            /**
             * Faire's discount has to be on both: CalculateInvoiceTotals deducts the invoice's
             * own amount_off, and the order's is reasserted because everything above this point
             * recalculates totals and must never be allowed to drop it.
             */
            $order->update(['amount_off' => $amountOff]);
            $invoice->update(['amount_off' => $amountOff]);

            CalculateInvoiceTotals::run($invoice);
            $invoice->refresh();
            $invoice->update([
                'tax_amount'   => $faireTaxAmount,
                'total_amount' => $invoice->net_amount + $faireTaxAmount,
            ]);
        }
    }

    public function getFaireTaxAmount(array $orderFaireData, Shop $shop): float
    {
        $tax      = 0;
        $exchange = 1;

        $currencyCode = Arr::get($orderFaireData, 'payout_costs.taxes.0.value.currency');
        if ($currencyCode && $currencyCode != $shop->currency->code) {
            $currency = Currency::where('code', $currencyCode)->first();
            if ($currency) {
                $exchange = GetCurrencyExchange::run($currency, $shop->currency);
            }
        }

        foreach (Arr::get($orderFaireData, 'payout_costs.taxes', []) as $taxData) {
            $tax += Arr::get($taxData, 'value.amount_minor', 0) / 100;
        }

        return round($tax * $exchange, 2);
    }

    public string $commandSignature = 'faire:update_order {order?}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('order')) {
            $order = Order::where('slug', $command->argument('order'))->firstOrFail();
            $command->info("Updating order {$order->shop->slug} $order->slug");
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

    public function getCategoryTaxCategory(Order $order, array $orderFaireData): TaxCategory
    {
        $tax  = 0;
        $isRe = false;
        foreach (Arr::get($orderFaireData, 'payout_costs.taxes', []) as $taxData) {
            if (Arr::get($taxData, 'tax_type') == 'RECARGO' && Arr::get($taxData, 'value.amount_minor', 0) > 0) {
                $isRe = true;
            }

            $tax += Arr::get($taxData, 'value.amount_minor', 0) / 100;
        }

        if ($tax == 0) {
            if (IsEuropeanUnion::run($order->shop->organisation->country->code)) {
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
                isRe: $isRe,
            );
        }

        return $taxCategory;
    }

    public function addTransaction($item, $shop, $transactionCommissionsFactor): array
    {
        return GetFaireOrdersInShop::make()->processFaireOrderItem($item, $shop, $transactionCommissionsFactor);
    }

    public function createDeliveryNoteItem(Order $order, Transaction $transaction): void
    {
        DB::transaction(function () use ($order, $transaction) {
            $deliveryNote = $this->lockWorkableDeliveryNote($order);
            if (!$deliveryNote) {
                return;
            }

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

            /*
             * A line the warehouse has never seen cannot be picked from a note that is already
             * packed, so the note goes back far enough for the new line to be worked.
             */
            $this->walkDeliveryNoteBackToPicking($deliveryNote->refresh(), true, false, null);
        });
    }

    /**
     * Walking a note back deletes packings and moves the order's state, and this runs unattended
     * over every Faire order, so the note is locked for the duration and the whole walk commits or
     * none of it does. Without it a throw further down the poll leaves a half unpacked note.
     */
    protected function lockWorkableDeliveryNote(Order $order): ?DeliveryNote
    {
        return $order->deliveryNotes()->whereNotIn('state', [
            DeliveryNoteStateEnum::CANCELLED,
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED,
        ])->where('type', DeliveryNoteTypeEnum::ORDER)->lockForUpdate()->first();
    }

    public function updateDeliveryNoteItem(Order $order, Transaction $transaction): void
    {
        /*
         * Faire is the source of truth: the buyer edits the order whatever the warehouse has
         * already done to it, so every state a note can still be worked in has to accept the
         * change. Only the terminal ones are refused, and what the pickers already did is walked
         * back properly rather than overwritten.
         */
        $needsCreate = DB::transaction(function () use ($order, $transaction) {
            $deliveryNote = $this->lockWorkableDeliveryNote($order);
            if (!$deliveryNote) {
                return false;
            }

            $deliveryNoteItem = $deliveryNote->deliveryNoteItems()->where('transaction_id', $transaction->id)->first();
            if (!$deliveryNoteItem) {
                return true;
            }

            $product   = Product::find($transaction->model_id);
            $orgStocks = $product->orgStocks->keyBy('id');

            $createdItem = false;
            foreach ($orgStocks as $orgStock) {
                $exists = $deliveryNote->deliveryNoteItems()
                    ->where('org_stock_id', $orgStock->id)
                    ->where('transaction_id', $transaction->id)
                    ->exists();

                if (!$exists) {
                    $quantity = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);

                    StoreDeliveryNoteItem::make()->action($deliveryNote, [
                        'org_stock_id'               => $orgStock->id,
                        'transaction_id'             => $transaction->id,
                        'quantity_required'          => $quantity,
                        'original_quantity_required' => $quantity,
                    ]);
                    $createdItem = true;
                }
            }

            $this->syncDeliveryNote($deliveryNote->refresh(), $transaction, $orgStocks, null);

            if ($createdItem) {
                $this->walkDeliveryNoteBackToPicking($deliveryNote->refresh(), true, false, null);
            }

            return false;
        });

        if ($needsCreate) {
            $this->createDeliveryNoteItem($order, $transaction);
        }
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
