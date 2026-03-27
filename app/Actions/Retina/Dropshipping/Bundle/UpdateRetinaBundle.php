<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Dropshipping\Bundle\UpdateBundle;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Bundle;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaBundle extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Bundle $bundle, array $modelData): Bundle
    {
        return UpdateBundle::make()->action($bundle, $modelData);
    }

    public function rules(): array
    {
        return UpdateBundle::make()->rules();
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Bundle $bundle, ActionRequest $request): Bundle
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($bundle, $this->validatedData);
    }
}
