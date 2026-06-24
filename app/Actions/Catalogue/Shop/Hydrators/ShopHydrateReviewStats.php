<?php

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\ShopReview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $shopId): string
    {
        return (string) ($shopId ?? 'empty');
    }

    public function handle(int|null $shopId): void
    {
        if (!$shopId) {
            return;
        }

        $shop = Shop::query()->find($shopId);
        if (!$shop) {
            return;
        }

        $stats = $this->buildReviewStats(
            ShopReview::query()->where('shop_id', $shop->id)
        );

        $shop->reviewStats()->updateOrCreate([], $stats);
    }
}
