<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-11h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Actions\Retina\Dropshipping\Portfolio\ClearCustomerSalesChannelImagesZip;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaProductManual extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $this->retinaCustomerOwnsRouteModels($request);
    }

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {

        ClearCustomerSalesChannelImagesZip::run($customerSalesChannel);

        DB::transaction(function () use ($customerSalesChannel, $modelData) {
            StoreMultiplePortfolios::run($customerSalesChannel, $modelData);
        });
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        $this->initialisationActions($customerSalesChannel->customer, $modelData);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
