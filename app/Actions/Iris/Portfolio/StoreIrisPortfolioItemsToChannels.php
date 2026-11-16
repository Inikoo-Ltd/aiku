<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:45:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Portfolio;

use App\Actions\Dropshipping\Portfolio\CheckNewPortfoliosInPlatform;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreIrisPortfolioItemsToChannels extends IrisAction
{
    /**
     * @return array<int, int> the products newly added to at least one of the channels
     *
     * @throws \Throwable
     */
    public function handle(Collection $channels, array $itemIds): array
    {
        $channels = $channels->filter(
            fn ($customerSalesChannel) => $customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL
                || $customerSalesChannel->can_connect_to_platform
        );

        if ($channels->isEmpty()) {
            return [];
        }

        $items = Product::whereIn('id', $itemIds)
            ->where('is_for_sale', true)
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->get();

        $existingPortfolios = Portfolio::whereIn('customer_sales_channel_id', $channels->pluck('id'))
            ->whereIn('item_id', $items->pluck('id'))
            ->where('item_type', 'Product')
            ->get()
            ->keyBy(fn ($p) => "$p->customer_sales_channel_id-$p->item_id");

        $newProductIds   = [];
        $newPortfolioIds = DB::transaction(function () use ($channels, $items, $existingPortfolios, &$newProductIds) {
            $newPortfolioIds = [];
            $channels->each(function ($customerSalesChannel) use ($items, $existingPortfolios, &$newPortfolioIds, &$newProductIds) {
                $items->each(function ($item) use ($customerSalesChannel, $existingPortfolios, &$newPortfolioIds, &$newProductIds) {
                    $compositeKey = $customerSalesChannel->id . '-' . $item->id;
                    if ($existingPortfolios->has($compositeKey)) {
                        /** @var Portfolio $portfolio */
                        $portfolio = $existingPortfolios->get($compositeKey);
                        if (!$portfolio->status) {
                            UpdatePortfolio::make()->action($portfolio, ['status' => true]);
                            $newProductIds[] = $item->id;
                        }
                    } else {
                        $newPortfolioIds[$customerSalesChannel->id][] = StorePortfolio::make()->action($customerSalesChannel, $item, [], checkInPlatform: false)->id;
                        $newProductIds[]                              = $item->id;
                    }
                });
            });

            return $newPortfolioIds;
        });

        $channels->each(function ($customerSalesChannel) use ($newPortfolioIds) {
            if (isset($newPortfolioIds[$customerSalesChannel->id]) && StorePortfolio::checksPortfoliosInPlatform($customerSalesChannel->platform->type)) {
                CheckNewPortfoliosInPlatform::dispatch($customerSalesChannel, $newPortfolioIds[$customerSalesChannel->id])->afterCommit();
            }
        });

        return array_values(array_unique($newProductIds));
    }
}
