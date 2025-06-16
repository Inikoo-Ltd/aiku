<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-11h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\Dropshipping\Aiku\StoreMultipleManualPortfolios;
use App\Actions\Dropshipping\Shopify\Product\StorePortfolioShopify;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaProductManual extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        DB::transaction(function () use ($customerSalesChannel, $modelData) {
            if ($customerSalesChannel->platform_user_type == "ShopifyUser") {
                StorePortfolioShopify::run($customerSalesChannel->user, $modelData);
            } else {
                StoreMultipleManualPortfolios::run($customerSalesChannel, $modelData);
            }
        });
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        $this->initialisationActions($customerSalesChannel->customer, $modelData);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
