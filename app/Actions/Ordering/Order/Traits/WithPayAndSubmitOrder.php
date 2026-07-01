<?php

namespace App\Actions\Ordering\Order\Traits;

use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Order\WithOrderForbiddenCountryCheck;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Models\Ordering\Order;
use Exception;

trait WithPayAndSubmitOrder
{
    use WithOrderForbiddenCountryCheck;

    public function payAndSubmitOrder(Order $order)
    {
        $isForbidden = $this->isForbidden($order);

        // If forbidden, do not allow payment. So that it stucks at submit order only, and not in warehouse
        if (!$isForbidden) {
            try {
                PayOrderAsync::run($order);
            } catch (Exception $e) {
                Sentry::captureException($e);
            }
        }

        return SubmitOrder::make()->action($order);
    }
}
