<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Dropshipping\Bundle\CalculateBundleItemPriceDetails;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class CalculateRetinaBundleItemPriceDetails extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        return CalculateBundleItemPriceDetails::run($customerSalesChannel, $modelData);
    }

    public function rules(): array
    {
        return CalculateBundleItemPriceDetails::make()->rules();
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
