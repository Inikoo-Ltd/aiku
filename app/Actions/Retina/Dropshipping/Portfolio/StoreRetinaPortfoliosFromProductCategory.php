<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-11h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StoreMultiplePortfolios;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaPortfoliosFromProductCategory extends RetinaAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, ProductCategory $productCategory): void
    {
        $portfolios = $customerSalesChannel->portfolios->where('item_type', 'Product');
        $portfolioItemIds = $portfolios->pluck('item_id')->all();

        $products = $productCategory->getProducts()->reject(function ($product) use ($portfolioItemIds) {
            return in_array($product->id, $portfolioItemIds);
        });

        $data = $products->pluck('id')->all();

        DB::transaction(function () use ($customerSalesChannel, $data) {
            StoreMultiplePortfolios::run($customerSalesChannel, $data);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $productCategory);
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, ProductCategory $productCategory): void
    {
        $this->initialisationActions($customerSalesChannel->customer, []);

        $this->handle($customerSalesChannel, $productCategory);
    }
}
