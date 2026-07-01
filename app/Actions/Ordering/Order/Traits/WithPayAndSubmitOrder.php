<?php

/*
 * Author Louis Perez
 * Created on 01-07-2026-11h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

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
