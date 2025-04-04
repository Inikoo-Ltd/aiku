<?php

namespace App\Actions\Accounting\Payment\UI;

use App\Http\Resources\Accounting\InvoiceResource;
use App\Http\Resources\Accounting\PaymentAccountResource;
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
        $parentResource = null;
        $parent         = $payment->invoices()->first() ?? $payment->orders()->first();
        if ($parent instanceof Order) {
            $parentResource = OrderResource::make($parent);
        } elseif ($parent instanceof Invoice) {
            $parentResource = InvoiceResource::make($parent);
        } else {
            $parentResource = null;
        }

        return [
            'parent_type'    => $parent ? class_basename($parent) : null,
            'amount'         => $payment->amount,
            'state'          => $payment->state,
            'customer'       => CustomerResource::make($payment->customer),
            'parent_data'    => $parentResource,
            'currency'       => CurrencyResource::make($payment->currency),
            'paymentAccount' => PaymentAccountResource::make($payment->paymentAccount),
        ];
    }
}
