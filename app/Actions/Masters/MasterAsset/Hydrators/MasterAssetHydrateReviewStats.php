<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Masters\MasterAsset;
use App\Models\Reviews\ProductReview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $masterAssetId): string
    {
        return (string) ($masterAssetId ?? 'empty');
    }

    public function handle(int|null $masterAssetId): void
    {
        if (!$masterAssetId) {
            return;
        }

        $masterAsset = MasterAsset::query()->find($masterAssetId);
        if (!$masterAsset) {
            return;
        }

        $stats = $this->buildReviewStats(
            ProductReview::query()->where('master_product_id', $masterAsset->id)
        );

        $masterAsset->reviewStats()->updateOrCreate([], $stats);
    }
}
