<?php

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Masters\MasterProductCategory;
use App\Models\Reviews\ProductCategoryReview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterProductCategoryHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $masterProductCategoryId): string
    {
        return (string) ($masterProductCategoryId ?? 'empty');
    }

    public function handle(int|null $masterProductCategoryId): void
    {
        if (!$masterProductCategoryId) {
            return;
        }

        $masterProductCategory = MasterProductCategory::query()->find($masterProductCategoryId);
        if (!$masterProductCategory) {
            return;
        }

        $stats = $this->buildReviewStats(
            ProductCategoryReview::query()->where('master_product_category_id', $masterProductCategory->id)
        );

        $masterProductCategory->reviewStats()->updateOrCreate([], $stats);
    }
}
