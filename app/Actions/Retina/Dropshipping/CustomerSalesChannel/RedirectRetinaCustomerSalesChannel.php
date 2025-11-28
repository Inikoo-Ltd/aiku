<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;

class RedirectRetinaCustomerSalesChannel extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel)
    {
        return redirect()->route('retina.dropshipping.customer_sales_channels.show', [
            'customerSalesChannel' => $customerSalesChannel,
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel)
    {
        return $this->handle($customerSalesChannel);
    }
}
