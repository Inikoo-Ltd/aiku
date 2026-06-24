<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:45:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Portfolio;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreIrisPortfolioItemsToChannels extends IrisAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Collection $channels, array $itemIds): void
    {
        $items = Product::whereIn('id', $itemIds)
            ->where('is_for_sale', true)
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->get();

        $existingPortfolios = Portfolio::whereIn('customer_sales_channel_id', $channels->pluck('id'))
            ->whereIn('item_id', $items->pluck('id'))
            ->where('item_type', 'Product')
            ->get()
            ->keyBy(fn ($p) => "$p->customer_sales_channel_id-$p->item_id");

        DB::transaction(function () use ($channels, $items, $existingPortfolios) {
            $channels->each(function ($customerSalesChannel) use ($items, $existingPortfolios) {
                $items->each(function ($item) use ($customerSalesChannel, $existingPortfolios) {
                    $compositeKey = $customerSalesChannel->id . '-' . $item->id;
                    if ($existingPortfolios->has($compositeKey)) {
                        /** @var Portfolio $portfolio */
                        $portfolio = $existingPortfolios->get($compositeKey);
                        if (!$portfolio->status) {
                            UpdatePortfolio::make()->action($portfolio, ['status' => true]);
                        }
                    } else {
                        StorePortfolio::make()->action($customerSalesChannel, $item, []);
                    }
                });
            });
        });
    }
}
