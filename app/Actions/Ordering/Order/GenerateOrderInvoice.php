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
use App\Models\Helpers\Address;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class GenerateOrderInvoice extends OrgAction
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


            $invoiceData = [
                'in_process'                => false,
                'reference'                 => $order->reference,
                'currency_id'               => $order->currency_id,
                'billing_address'           => new Address($billingAddress->getFields()),
                'type'                      => InvoiceTypeEnum::INVOICE,
                'net_amount'                => $order->net_amount,
                'total_amount'              => $order->total_amount,
                'gross_amount'              => $order->gross_amount,
                'rental_amount'             => 0,
                'goods_amount'              => $order->goods_amount,
                'services_amount'           => $order->services_amount,
                'charges_amount'            => $order->charges_amount,
                'shipping_amount'           => $order->shipping_amount,
                'insurance_amount'          => $order->insurance_amount,
                'tax_amount'                => $order->tax_amount,
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
                    StoreInvoiceTransaction::make()->action($invoice, $transaction->historicAsset, $data);
                }
            }

            foreach ($order->payments as $payment) {
                AttachPaymentToInvoice::make()->action($invoice, $payment, []);
            }

            $invoice->refresh();

            return $invoice;
        });
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
