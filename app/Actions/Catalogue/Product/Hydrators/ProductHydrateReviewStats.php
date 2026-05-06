<?php

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Catalogue\Product;
use App\Models\Reviews\ProductReview;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $productId): string
    {
        return (string) ($productId ?? 'empty');
    }

    public function handle(int|null $productId): void
    {
        if (!$productId) {
            return;
        }

        $product = Product::query()->find($productId);
        if (!$product) {
            return;
        }

        $stats = $this->buildReviewStats(
            ProductReview::query()->where('product_id', $product->id)
        );

        $product->reviewStats()->updateOrCreate([], $stats);
    }
}
