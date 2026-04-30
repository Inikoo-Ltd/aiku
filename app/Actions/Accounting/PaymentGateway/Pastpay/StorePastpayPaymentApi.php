<?php

namespace App\Actions\Accounting\PaymentGateway\Pastpay;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentGateway\Paypal\Orders\StoreOrderToPaypal;
use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePastpayPaymentApi
{
    use AsAction;
    use WithActionUpdate;
    use WithPastpayConfiguration;

    public function handle(Order $order, array $modelData)
    {
        $this->shop = $order->shop;
    }
}

