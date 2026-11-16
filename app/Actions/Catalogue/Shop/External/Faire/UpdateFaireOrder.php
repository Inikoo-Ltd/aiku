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
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromShipping;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteLineCreation;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\Authorisations\Ordering\WithOrderPaymentAuthorisation;

class UpdateFaireOrder extends OrgAction
{
    use WithDeliveryNoteLineCreation;
    use WithOrderPaymentAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): void
    {
        if ($order->state == OrderStateEnum::CANCELLED) {
            return;
        }

        $shop = $order->shop;

        $orderFaireData = $shop->getFaireOrder($order->external_id);

        $this->syncCustomerCompanyName($order, $orderFaireData);

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

            /**
             * The line is restated in today's units, so its submitted snapshot is restated with it.
             * The invoice prices a submitted line as submitted net / submitted quantity x picked: a
             * product flipped from a pack of 4 to singles left "1 for 14.72" beside 4 picked and
             * billed 58.88 (HELP-4266).
             */
            if ($transaction->submitted_at) {
                $dataToUpdate['submitted_quantity_ordered'] = $quantity;
                $dataToUpdate['submitted_gross_amount']     = $netAmount;
                $dataToUpdate['submitted_net_amount']       = $netAmount;
            }


            if ($order->state == OrderStateEnum::DISPATCHED) {
                $dataToUpdate['quantity_dispatched'] = $quantity;
            }


            $transaction->update($dataToUpdate);

            $this->updateDeliveryNoteItem($order, $transaction);

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)
                ->whereRelation('invoice', 'type', InvoiceTypeEnum::INVOICE)
                ->orderBy('id')
                ->first();

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


        $faireStatedTaxAmount = $this->getFaireTaxAmount($orderFaireData, $shop);
        $faireTaxAmount       = round($faireStatedTaxAmount + $this->getFaireDamagedTaxAmount($order, $orderFaireData), 2);
        $order->update(['data->marketplace_tax_amount' => $faireTaxAmount]);

        $taxCategory = $this->getCategoryTaxCategory($order, $orderFaireData);

        $order = UpdateOrder::run($order, [
            'tax_category_id' => $taxCategory->id,
        ]);

        $this->syncFaireShipping($order, $orderFaireData);

        CalculateOrderTotalAmounts::run($order);

        $invoice = $this->getInvoiceToDiscount($order);
        if ($invoice) {
            /**
             * Faire's discount has to be on both: CalculateInvoiceTotals deducts the invoice's
             * own amount_off, and the order's is reasserted because everything above this point
             * recalculates totals and must never be allowed to drop it.
             */
            $order->update(['amount_off' => $amountOff]);
            $invoice->update(['amount_off' => $amountOff]);

            $this->invoiceDispatchedLinesMissingFromInvoice($order, $invoice);

            $faireTaxAmount = round($faireStatedTaxAmount + $this->getFaireDamagedTaxAmount($order, $orderFaireData), 2);
            $order->update(['data->marketplace_tax_amount' => $faireTaxAmount]);

            CalculateInvoiceTotals::run($invoice);
            $invoice->refresh();
            $invoice->update([
                'tax_amount'   => $faireTaxAmount,
                'total_amount' => $invoice->net_amount + $faireTaxAmount,
            ]);
        }

        /**
         * Last, deliberately: removing the lines Faire never billed recalculates the order, which
         * derives tax from Aiku's own rates. Faire states the tax it paid, so it is asserted after
         * everything that could recompute it.
         */
        $order->refresh();
        $order->update([
            'tax_amount'   => $faireTaxAmount,
            'total_amount' => $order->net_amount + $faireTaxAmount,
        ]);

        $this->reportDivergenceFromFaire($order->refresh(), $invoice?->refresh(), $orderFaireData, $amountOff, $faireTaxAmount);
    }

    /**
     * Retailers rename their store on Faire, and the name was only ever read when a customer was first
     * imported, so a renamed store kept its old name in Aiku (HELP-2724). The order carries the current
     * store name on its address, so no extra call to Faire is needed.
     */
    public function syncCustomerCompanyName(Order $order, array $orderFaireData): void
    {
        $companyName = $this->getFaireCompanyName($orderFaireData);
        $customer    = $order->customer;

        if ($companyName === null || !$customer || $customer->company_name === $companyName) {
            return;
        }

        UpdateCustomer::make()->action(customer: $customer, modelData: ['company_name' => $companyName], strict: false);
    }

    public function getFaireCompanyName(array $orderFaireData): ?string
    {
        $companyName = trim((string)Arr::get($orderFaireData, 'address.company_name', ''));

        return $companyName === '' ? null : $companyName;
    }

    /**
     * Faire is the source of truth, so whatever it says the order is worth is what we must hold.
     * Nothing compared the two until now, which is how six months of drift went unnoticed: every
     * divergence was found by a support ticket instead. Logged rather than thrown, because a wrong
     * total must not stop the sync from importing the rest of the order.
     */
    public function reportDivergenceFromFaire(Order $order, ?Invoice $invoice, array $orderFaireData, float $amountOff, float $faireTaxAmount): void
    {
        /**
         * Faire charges the retailer shipping too: it is not in the items but its VAT is in the
         * taxes array (taxable_item_type SHIPPING), so the expected total includes the order's
         * shipping line. subtotal_after_brand_discounts fits nowhere here - it is net of both
         * shipping and damaged_and_missing_items.
         */
        $expectedNet   = round($this->getFaireItemsNet($orderFaireData, $order->shop) - $amountOff + $order->shipping_amount + $order->charges_amount, 2);
        $expectedTotal = round($expectedNet + $faireTaxAmount, 2);

        foreach (['order' => $order, 'invoice' => $invoice] as $side => $model) {
            if (!$model || abs(round($model->total_amount - $expectedTotal, 2)) < 0.01) {
                continue;
            }

            Log::warning('Faire total mismatch', [
                'faire_order'    => $order->slug,
                'shop'           => $order->shop->code,
                'side'           => $side,
                'faire_total'    => $expectedTotal,
                'aiku_total'     => (float)$model->total_amount,
                'difference'     => round($model->total_amount - $expectedTotal, 2),
                'faire_net'      => $expectedNet,
                'faire_tax'      => $faireTaxAmount,
                'faire_discount' => $amountOff,
                'damaged'        => Arr::get($orderFaireData, 'payout_costs.damaged_and_missing_items.amount_minor', 0) / 100,
            ]);
        }
    }

    /**
     * What Faire says the goods are worth: the sum of its own line items. This is the invoice figure,
     * NOT payout_costs.subtotal_after_brand_discounts, which is already net of damaged_and_missing_items
     * that Faire withholds from the payout and Aiku does not model.
     */
    public function getFaireItemsNet(array $orderFaireData, Shop $shop): float
    {
        $net      = 0;
        $exchange = 1;

        $currencyCode = Arr::get($orderFaireData, 'items.0.price.currency');
        if ($currencyCode && $currencyCode != $shop->currency->code) {
            $currency = Currency::where('code', $currencyCode)->first();
            if ($currency) {
                $exchange = GetCurrencyExchange::run($currency, $shop->currency);
            }
        }

        foreach (Arr::get($orderFaireData, 'items', []) as $item) {
            $net += Arr::get($item, 'quantity', 0) * Arr::get($item, 'price.amount_minor', 0) / 100;
        }

        return round($net * $exchange, 2);
    }

    /**
     * A line Faire adds to an already-invoiced order gets a transaction and a delivery note item
     * but never an invoice line, so it ships and is never billed. Only dispatched lines are added:
     * a line still waiting is legitimately absent from the invoice until it goes out.
     */
    public function invoiceDispatchedLinesMissingFromInvoice(Order $order, Invoice $invoice): void
    {
        $missing = $order->transactions()
            ->where('model_type', 'Product')
            ->where('quantity_dispatched', '>', 0)
            ->whereDoesntHave('invoiceTransaction', fn ($query) => $query->whereRelation('invoice', 'type', InvoiceTypeEnum::INVOICE))
            ->get();

        foreach ($missing as $transaction) {
            $dispatchedRatio = $transaction->quantity_ordered > 0
                ? min(1, $transaction->quantity_dispatched / $transaction->quantity_ordered)
                : 1;

            StoreInvoiceTransaction::make()->action($invoice, $transaction, [
                'tax_category_id' => $transaction->tax_category_id,
                'quantity'        => $transaction->quantity_dispatched,
                'gross_amount'    => round($transaction->gross_amount * $dispatchedRatio, 2),
                'net_amount'      => round($transaction->net_amount * $dispatchedRatio, 2),
            ]);
        }
    }

    /**
     * Faire's discount belongs on the order's invoice, never on a credit note: refunds
     * are raised from their own transactions and writing amount_off onto one turns it positive.
     */
    public function getInvoiceToDiscount(Order $order): ?Invoice
    {
        return $order->invoices()
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->orderBy('id')
            ->first();
    }

    /**
     * Faire only adds the VAT on shipping once the shipment is pushed at dispatch, after the last full
     * sync, so dispatched orders kept the items-only tax (HELP-3694). Only that exact case is fixed:
     * any other difference from Faire on an issued invoice is left alone, never rewritten.
     */
    public function syncFaireTax(Order $order): void
    {
        $orderFaireData = $order->shop->getFaireOrder($order->external_id);
        $this->syncFaireShipping($order, $orderFaireData);

        $taxes = collect(Arr::get($orderFaireData, 'payout_costs.taxes', []));
        if ($taxes->isEmpty()) {
            return;
        }

        $damagedTax   = $this->getFaireDamagedTaxAmount($order, $orderFaireData);
        $itemsTaxData = ['payout_costs' => ['taxes' => $taxes->where('taxable_item_type', 'ORDER_ITEM')->values()->all()]];
        $itemsTax     = round($this->getFaireTaxAmount($itemsTaxData, $order->shop) + $damagedTax, 2);
        $faireTax     = round($this->getFaireTaxAmount($orderFaireData, $order->shop) + $damagedTax, 2);

        $order->refresh();
        if ($faireTax - $itemsTax < 0.01 || abs((float)$order->tax_amount - $itemsTax) >= 0.01) {
            return;
        }

        $order->update([
            'data->marketplace_tax_amount' => $faireTax,
            'tax_amount'                   => $faireTax,
            'total_amount'                 => $order->net_amount + $faireTax,
        ]);

        $invoice = $this->getInvoiceToDiscount($order);
        if ($invoice && abs((float)$invoice->tax_amount - $itemsTax) < 0.01) {
            $invoice->update([
                'tax_amount'   => $faireTax,
                'total_amount' => $invoice->net_amount + $faireTax,
            ]);
        }
    }

    /**
     * Faire pays shipping only on orders we ship ourselves: staff enter the cost on Faire, which
     * reimburses it in the payout as shipments.maker_cost. Nobody types it in Aiku (HELP-3906).
     * It is copied only onto an order that has no shipping yet: an amount already on an order or
     * an issued invoice is never rewritten, and the tax stays whatever Faire stated.
     */
    public function syncFaireShipping(Order $order, array $orderFaireData): void
    {
        $faireShipping = $this->getFaireShippingAmount($orderFaireData, $order->shop);
        if ($faireShipping < 0.01 || (float)$order->shipping_amount >= 0.01) {
            return;
        }

        $this->setFaireShipping($order, $faireShipping);
    }

    public function setFaireShipping(Order $order, float $faireShipping): void
    {
        $orderTax = (float)$order->tax_amount;
        UpdateOrderShippingEngineAsManual::run($order, ['shipping_amount' => $faireShipping]);
        $order->refresh();
        $order->update([
            'tax_amount'   => $orderTax,
            'total_amount' => $order->net_amount + $orderTax,
        ]);

        $invoice = $this->getInvoiceToDiscount($order);
        if (!$invoice) {
            return;
        }

        $shippingLines = $invoice->invoiceTransactions()->where('model_type', 'ShippingZone')->get();
        if ($shippingLines->contains(fn (InvoiceTransaction $line) => abs((float)$line->net_amount) >= 0.01)) {
            return;
        }

        $invoiceTax = (float)$invoice->tax_amount;
        foreach ($shippingLines as $emptyShippingLine) {
            DeleteInvoiceTransaction::run($emptyShippingLine);
        }
        StoreInvoiceTransactionFromShipping::make()->action($invoice, null, [
            'tax_category_id' => $order->tax_category_id,
            'quantity'        => 1,
            'gross_amount'    => $faireShipping,
            'net_amount'      => $faireShipping,
        ]);

        CalculateInvoiceTotals::run($invoice);
        $invoice->refresh();
        $invoice->update([
            'tax_amount'   => $invoiceTax,
            'total_amount' => $invoice->net_amount + $invoiceTax,
        ]);
    }

    public function getFaireShippingAmount(array $orderFaireData, Shop $shop): float
    {
        $shipping = 0;
        $exchange = 1;

        $currencyCode = Arr::get($orderFaireData, 'shipments.0.maker_cost.currency');
        if ($currencyCode && $currencyCode != $shop->currency->code) {
            $currency = Currency::where('code', $currencyCode)->first();
            if ($currency) {
                $exchange = GetCurrencyExchange::run($currency, $shop->currency);
            }
        }

        foreach (Arr::get($orderFaireData, 'shipments', []) as $shipment) {
            $shipping += Arr::get($shipment, 'maker_cost.amount_minor', 0) / 100;
        }

        return round($shipping * $exchange, 2);
    }

    /**
     * A retailer's damaged or missing claim makes Faire withhold those items from the payout and
     * drop their VAT from payout_costs.taxes, while the invoice still bills them in full: the sale
     * stands and the claim is a refund. Faire takes the damaged items off at full list price, before
     * its discount, so the VAT it no longer states is the invoice's own lines for those items at
     * their rates. Only lines the invoice billed count: a missing item never dispatched was never
     * charged. A refresh after a claim so never lowers what the invoice charged (INI-131).
     */
    public function getFaireDamagedTaxAmount(Order $order, array $orderFaireData): float
    {
        $damagedItemIds = collect(Arr::get($orderFaireData, 'items', []))
            ->where('state', 'DAMAGED_OR_MISSING')
            ->pluck('id')
            ->all();

        if (empty($damagedItemIds)) {
            return 0.0;
        }

        return round((float)DB::table('invoice_transactions')
            ->join('invoices', 'invoices.id', 'invoice_transactions.invoice_id')
            ->join('tax_categories', 'tax_categories.id', 'invoice_transactions.tax_category_id')
            ->where('invoices.order_id', $order->id)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_transactions.deleted_at')
            ->whereIn('invoice_transactions.marketplace_id', $damagedItemIds)
            ->sum(DB::raw('invoice_transactions.net_amount * tax_categories.rate')), 2);
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

        /*
         * Only orders still in play. Run with no argument this walks every Faire order ever, and
         * re-deriving quantity_ordered from today's units against a sale made months ago rewrote
         * 348 dispatched delivery note lines and 34 already-issued invoices on 16 Aug 2026.
         */
        $orders = Order::whereIn('shop_id', $faireShops)
            ->whereNotIn('state', [
                OrderStateEnum::DISPATCHED,
                OrderStateEnum::FINALISED,
                OrderStateEnum::CANCELLED,
            ]);

        /** @var Order $order */
        foreach ($orders->get() as $order) {
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
                    $quantity = DeliveryNoteItem::requiredQuantity((float) $orgStock->pivot->quantity, (float) $transaction->quantity_ordered + (float) $transaction->quantity_bonus);

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
                $this->walkDeliveryNoteBackToPicking($deliveryNote->refresh(), true, null);
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
