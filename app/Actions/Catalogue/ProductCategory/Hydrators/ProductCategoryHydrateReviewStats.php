<?php

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Catalogue\ProductCategory;
use App\Models\Reviews\ProductCategoryReview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $productCategoryId): string
    {
        return (string) ($productCategoryId ?? 'empty');
    }

    public function handle(int|null $productCategoryId): void
    {
        if (!$productCategoryId) {
            return;
        }

        $productCategory = ProductCategory::query()->find($productCategoryId);
        if (!$productCategory) {
            return;
        }

        $stats = $this->buildReviewStats(
            ProductCategoryReview::query()->where('product_category_id', $productCategory->id)
        );

        $productCategory->reviewStats()->updateOrCreate([], $stats);
    }
}
