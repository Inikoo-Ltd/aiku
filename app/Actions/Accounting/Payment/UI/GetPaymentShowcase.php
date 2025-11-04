<?php

namespace App\Actions\Accounting\Payment\UI;

use App\Http\Resources\Accounting\CreditTransactionResource;
use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\PaymentAccountResource;
use App\Http\Resources\Accounting\PaymentServiceProviderResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPaymentShowcase
{
    use AsObject;

    public function handle(Payment $payment): array
    {
        $invoiceData = null;
        $orderData   = null;
        $invoice     = $payment->invoices()->first();
        if ($invoice) {
            $invoiceData = InvoiceResource::make($invoice);
        } else {
            $order = $payment->orders()->first();
            if ($order) {
                $orderData = OrderResource::make($order)->toArray(request());
            }
        }


        $paymentServiceProvider = null;
        if ($serviceProvider = $payment->orgPaymentServiceProvider?->paymentServiceProvider) {
            $paymentServiceProvider = PaymentServiceProviderResource::make($serviceProvider);
        }

        $creditTransaction = null;
        if ($payment->creditTransaction) {
            $creditTransaction = CreditTransactionResource::make($payment->creditTransaction);
        }

        return [

            'amount'                 => $payment->amount,
            'date'                   => $payment->date,
            'state'                  => $payment->state,
            'customer'               => CustomerResource::make($payment->customer),
            'order_data'             => $orderData,
            'invoice_data'           => $invoiceData,
            'currency'               => CurrencyResource::make($payment->currency),
            'paymentAccount'         => PaymentAccountResource::make($payment->paymentAccount),
            'paymentServiceProvider' => $paymentServiceProvider,
            'credit_transaction'     => $creditTransaction
        ];
    }
}
