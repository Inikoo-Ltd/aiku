<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\Dropshipping\Bundle\CalculateBundleItemPriceDetails;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\CRM\BundleResource;
use App\Models\Bundle;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaBundle extends RetinaAction
{
    use WithActionUpdate;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Bundle $bundle): Bundle
    {
        $bundle->load([
            'bundleable.images',
            'bundleable.tradeUnits.images',
        ]);

        return $bundle;
    }

    public function jsonResponse(Bundle $bundle): BundleResource
    {
        return BundleResource::make($bundle);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Bundle $bundle, ActionRequest $request): Bundle
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($bundle);
    }
}
