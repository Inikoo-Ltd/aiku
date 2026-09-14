<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Dropshipping\Bundle\DeleteBundle;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Bundle;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaBundle extends RetinaAction
{
    use WithActionUpdate;

    private CustomerSalesChannel $customerSalesChannel;

    private Bundle $bundle;

    public function handle(Bundle $bundle): void
    {
        DeleteBundle::run($bundle);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer?->id
            && $this->bundle->customer_sales_channel_id == $this->customerSalesChannel->id;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Bundle $bundle, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->bundle               = $bundle;
        $this->initialisation($request);

        $this->handle($bundle);
    }
}
