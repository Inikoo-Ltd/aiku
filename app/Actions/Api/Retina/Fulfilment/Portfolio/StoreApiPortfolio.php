<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncAllRetinaStoredItemsToPortfolios;
use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\PortfolioResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class StoreApiPortfolio extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        SyncAllRetinaStoredItemsToPortfolios::make()->action($customerSalesChannel);
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromFulfilment($request);

        $this->handle($this->customerSalesChannel);
    }

    public function jsonResponse(): PortfolioResource
    {
        return JsonResource::make([
            'message' => __('Portfolio synced successfully'),
        ]);
    }
}
