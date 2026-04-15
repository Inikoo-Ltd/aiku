<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Dropshipping\Bundle\StoreOrUpdateBundle;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Bundle;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class StoreOrUpdateRetinaBundle extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;
    private bool $isUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): Bundle
    {
        return StoreOrUpdateBundle::make()->action($customerSalesChannel, $modelData);
    }

    public function rules(): array
    {
        return StoreOrUpdateBundle::make()->rules();
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Bundle
    {
        $id = $request->input('id');
        $this->isUpdate = (bool) $id;

        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
