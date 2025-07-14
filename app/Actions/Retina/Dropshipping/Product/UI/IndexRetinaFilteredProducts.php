<?php

/*
 * author Arya Permana - Kirin
 * created on 21-05-2025-13h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Product\UI;

use App\Actions\CRM\Customer\UI\GetProductsForPortfolioSelect;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\ProductsForPortfolioSelectResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaFilteredProducts extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        return GetProductsForPortfolioSelect::run($customerSalesChannel, $prefix);
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsForPortfolioSelectResource::collection($products);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

}
