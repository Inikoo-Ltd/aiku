<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class CreateNewAllPortfoliosToWix extends RetinaAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $portfolios = $customerSalesChannel
            ->portfolios()
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('products as p')
                    ->whereColumn('p.id', 'portfolios.item_id')
                    ->whereNot('p.state', ProductStateEnum::DISCONTINUED->value)
                    ->where('p.is_for_sale', true);
            })
            ->where('status', true)
            ->where('platform_status', false)
            ->pluck('id');

        CreateNewBulkPortfoliosToWix::dispatch($customerSalesChannel, [
            'portfolios' => $portfolios->toArray(),
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel);
    }
}
