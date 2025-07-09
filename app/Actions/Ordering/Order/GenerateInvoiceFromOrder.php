<?php

/*
 * author Arya Permana - Kirin
 * created on 04-07-2025-12h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromAdjustment;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromCharge;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromShipping;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class GenerateInvoiceFromOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {

            $billingAddress = $order->billingAddress;
            $updatedData = [];
            if($order->deliveryNotes)
            {
                $deliveryNote = $order->deliveryNotes->first();
                $updatedData = $this->recalculateTotals($deliveryNote);
            }

            $invoiceData = [
                'in_process'                => false,
                'reference'                 => $order->reference,
                'currency_id'               => $order->currency_id,
                'billing_address'           => new Address($billingAddress->getFields()),
                'type'                      => InvoiceTypeEnum::INVOICE,
                'net_amount'                => Arr::get($updatedData, 'net_amount', $order->net_amount),
                'total_amount'              => Arr::get($updatedData, 'total_amount', $order->total_amount),
                'gross_amount'              => Arr::get($updatedData, 'gross_amount', $order->gross_amount),
                'rental_amount'             => 0,
                'goods_amount'              => Arr::get($updatedData, 'goods_amount', $order->goods_amount),
                'services_amount'           => $order->services_amount,
                'charges_amount'            => $order->charges_amount,
                'shipping_amount'           => $order->shipping_amount,
                'insurance_amount'          => $order->insurance_amount,
                'tax_amount'                => Arr::get($updatedData, 'tax_amount', $order->tax_amount),
                'customer_sales_channel_id' => $order->customer_sales_channel_id,
                'platform_id'               => $order->platform_id,
                'footer'                    => $order->shop->invoice_footer ?? ''
            ];

            $invoice = StoreInvoice::make()->action(parent: $order, modelData: $invoiceData);

            $transactions = $order->transactions;

            foreach ($transactions as $transaction) {
                $data = [
                    'tax_category_id' => $transaction->order->tax_category_id,
                    'quantity'        => $transaction->quantity_ordered,
                    'gross_amount'    => $transaction->gross_amount,
                    'net_amount'      => $transaction->net_amount,
                ];

                if ($transaction->model_type == 'Adjustment') {
                    /** @var Adjustment $adjustment */
                    $adjustment = Adjustment::find($transaction->model_id);
                    StoreInvoiceTransactionFromAdjustment::make()->action($invoice, $adjustment, $data);
                } elseif ($transaction->model_type == 'Charge') {
                    StoreInvoiceTransactionFromCharge::make()->action(
                        invoice: $invoice,
                        charge: $transaction->model,
                        modelData: $data
                    );
                } elseif ($transaction->model_type == 'ShippingZone') {
                    StoreInvoiceTransactionFromShipping::make()->action($invoice, $transaction->model, $data);
                } else {
                    $updatedData = $this->recalculateTransactionTotals($transaction);
                    StoreInvoiceTransaction::make()->action($invoice, $transaction->historicAsset, $updatedData);
                }
            }

            foreach ($order->payments as $payment) {
                AttachPaymentToInvoice::make()->action($invoice, $payment, []);
            }


            $invoice->refresh();

            return $invoice;
        });
    }

    public function recalculateTotals(DeliveryNote $deliveryNote): array
    {
        $order = $deliveryNote->orders->first();

        $itemsNet   = 0;
        $itemsGross = 0;

        foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
            $totals = $this->recalculateTransactionTotals($transaction);
            $itemsNet   += $totals['net_amount'];
            $itemsGross += $totals['gross_amount'];
        }

        $tax = $order->taxCategory->rate;

        $netAmount = $itemsNet + $order->shipping_amount + $order->charges_amount;

        $taxAmount   = $netAmount * $tax;
        $totalAmount = $netAmount + $taxAmount;

        $data = [
            'net_amount'   => $netAmount,
            'total_amount' => $totalAmount,
            'tax_amount'   => $taxAmount,
            'goods_amount' => $itemsNet,
            'gross_amount' => $itemsGross,
        ];

        return $data;
    }

    public function recalculateTransactionTotals(Transaction $transaction): array
    {
        $data = [];
        $deliveryNoteItem = $transaction->deliveryNoteItem;

        $historicAsset    = $transaction->historicAsset;
        $net              = $historicAsset->price * $deliveryNoteItem->quantity_picked;
        $gross            = $historicAsset->price * $deliveryNoteItem->quantity_picked;

        $data = [
                'tax_category_id' => $transaction->order->tax_category_id,
                'quantity'        => $deliveryNoteItem->quantity_picked,
                'gross_amount'    => $gross,
                'net_amount'      => $net,
        ];

        return $data;
    }

    public function htmlResponse(Invoice $invoice): RedirectResponse
    {
        return Redirect::route('grp.org.accounting.invoices.show', [
            'organisation' => $invoice->shop->organisation->slug,
            'invoice'      => $invoice->slug
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Invoice
    {
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
