<?php

namespace App\Actions\Accounting\PaymentGateway\Pastpay;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

class StorePastpayPaymentApi
{
    use AsAction;
    use WithActionUpdate;
    use WithPastpayConfiguration;

    public function handle(Order $order, array $modelData)
    {
        $this->shop = $order->shop;

        return $this->pastpayInitiateOrder($order, $modelData);
    }
}
