<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\PortfolioResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Lorisleiva\Actions\ActionRequest;

class StoreApiPortfolio extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, Product|StoredItem $item): Portfolio
    {
        $portfolio = StorePortfolio::make()->action($customerSalesChannel, $item, []);

        return $portfolio;
    }
    /**
     * @throws \Throwable
     */
    public function asController(Product $product, ActionRequest $request): Portfolio
    {

        $this->initialisationFromDropshipping($request);

        return $this->handle($this->customerSalesChannel, $product, $this->validatedData);
    }

    public function jsonResponse(Portfolio $portfolio): PortfolioResource
    {
        return PortfolioResource::make($portfolio);
    }

}
