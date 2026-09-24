<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithRetinaRouteModelOwnershipCheck;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;

class RedirectRetinaCustomerSalesChannel extends RetinaAction
{
    use WithRetinaRouteModelOwnershipCheck;

    public function handle(CustomerSalesChannel $customerSalesChannel)
    {
        return redirect()->route('retina.dropshipping.customer_sales_channels.show', [
            'customerSalesChannel' => $customerSalesChannel,
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        abort_unless($this->retinaCustomerOwnsRouteModels($request), 403);

        return $this->handle($customerSalesChannel);
    }
}
