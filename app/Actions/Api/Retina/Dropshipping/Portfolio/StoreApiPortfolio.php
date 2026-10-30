<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
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
        /** @var Portfolio|null $portfolio */
        $portfolio = $customerSalesChannel->portfolios()
            ->where('item_type', class_basename($item))
            ->where('item_id', $item->id)
            ->first();

        if (!$portfolio) {
            return StorePortfolio::make()->action($customerSalesChannel, $item, []);
        }

        if (!$portfolio->status) {
            $portfolio = UpdatePortfolio::make()->action($portfolio, ['status' => true]);
        }

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
        return PortfolioResource::make($portfolio->load('item'));
    }

}
