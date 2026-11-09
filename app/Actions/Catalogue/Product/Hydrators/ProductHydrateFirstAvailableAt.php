<?php

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateFirstAvailableAt
{
    use AsAction;

    /**
     * Stamps, once and for good, the moment a product can first be ordered on the website:
     * for sale, in stock and on a live webpage. The NEW IN badge counts from it.
     * A product already ordered or invoiced before keeps the date of its first sale, so it is not shown as new.
     */
    public function handle(Product $product): Product
    {
        if ($product->first_available_at) {
            return $product;
        }

        if (!$product->is_for_sale || !$product->has_live_webpage || $product->status !== ProductStatusEnum::FOR_SALE) {
            return $product;
        }

        $product->update(['first_available_at' => self::firstSoldAt($product) ?? now()]);

        $newInPage = $product->shop?->website?->newInPage;
        if ($newInPage && ProductBadgeEnum::resolve($product->first_available_at, null) === ProductBadgeEnum::NEW_IN) {
            BreakWebpageCache::dispatch($newInPage);
        }

        return $product;
    }

    public static function firstSoldAt(Product $product): ?Carbon
    {
        if (!$product->asset_id) {
            return null;
        }

        return self::earliest([
            InvoiceTransaction::where('asset_id', $product->asset_id)->min('date'),
            Transaction::where('asset_id', $product->asset_id)->min('submitted_at'),
        ]);
    }

    public static function earliest(array $dates): ?Carbon
    {
        $dates = collect($dates)->filter()->map(fn ($date) => Carbon::parse($date));

        return $dates->isEmpty() ? null : $dates->min();
    }
}
