<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 00:07:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Concerns;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateImages;
use App\Actions\Catalogue\Product\UpdateProductWebImages;
use App\Actions\Helpers\Translations\TranslateProductImageAlts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoImage;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait CanCloneImages
{
    protected function dedupeAttachedImages(Model $model): void
    {
        $keepIds = DB::table('model_has_media')
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->id)
            ->selectRaw('min(id) as id')
            ->groupBy('media_id')
            ->pluck('id');

        DB::table('model_has_media')
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    protected function cloneImages(TradeUnit|MasterProductCategory|MasterAsset|MasterCollection|Model $source, Product|ProductCategory|Collection|Model $target): void
    {
        $this->dedupeAttachedImages($target);

        $currentPivots = DB::table('model_has_media')
            ->where('model_type', $target->getMorphClass())
            ->where('model_id', $target->id)
            ->get()
            ->keyBy('media_id');

        $images   = [];
        $position = 1;

        foreach ($source->images as $image) {
            $images[$image->id] = [
                'is_public'       => true,
                'scope'           => $image->pivot->scope === 'audio' ? 'audio' : 'photo',
                'sub_scope'       => $image->pivot->sub_scope,
                'organisation_id' => $target->organisation_id ?? null,
                'group_id'        => $target->group_id ?? null,
                'position'        => $position++,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}',
            ] + $this->captionToKeep($image->pivot->caption, $currentPivots->get($image->id));
        }

        $target->images()->sync($images);
    }

    /**
     * A caption edited by hand, or already translated from the same source caption, outlives the
     * sync that would otherwise put the source's own caption back.
     *
     * @return array{caption: string|null, source_caption: string|null, is_caption_reviewed: bool}
     */
    protected function captionToKeep(?string $sourceCaption, ?object $currentPivot): array
    {
        if ($currentPivot?->is_caption_reviewed) {
            return [
                'caption'             => $currentPivot->caption,
                'source_caption'      => $currentPivot->source_caption,
                'is_caption_reviewed' => true,
            ];
        }

        if ($currentPivot && $currentPivot->source_caption !== null && $currentPivot->source_caption === $sourceCaption) {
            return [
                'caption'             => $currentPivot->caption,
                'source_caption'      => $currentPivot->source_caption,
                'is_caption_reviewed' => false,
            ];
        }

        return [
            'caption'             => $sourceCaption,
            'source_caption'      => null,
            'is_caption_reviewed' => false,
        ];
    }

    protected function syncProductImages(TradeUnit|MasterAsset|Model $source, Product $product): void
    {
        if ($product->not_follow_master_media) {
            return;
        }

        $this->cloneImages($source, $product);

        TranslateProductImageAlts::dispatch($product);

        $product->update([
            'bucket_images'            => count($product->images) > 0 || !empty($product->video_url),
            'image_id'                 => $source->image_id,
            'front_image_id'           => $source->front_image_id,
            '34_image_id'              => $source->{'34_image_id'},
            'left_image_id'            => $source->left_image_id,
            'right_image_id'           => $source->right_image_id,
            'back_image_id'            => $source->back_image_id,
            'top_image_id'             => $source->top_image_id,
            'bottom_image_id'          => $source->bottom_image_id,
            'size_comparison_image_id' => $source->size_comparison_image_id,
            'art1_image_id'            => $source->art1_image_id,
            'art2_image_id'            => $source->art2_image_id,
            'art3_image_id'            => $source->art3_image_id,
            'art4_image_id'            => $source->art4_image_id,
            'art5_image_id'            => $source->art5_image_id,
            'lifestyle_image_id'       => $source->lifestyle_image_id,
            'audio_id'                 => $source->audio_id,
            'video_url'                => $source->video_url,
        ]);

        $changed = Arr::except($product->getChanges(), ['updated_at', 'last_fetched_at']);

        if (!empty($changed)) {
            BreakProductInWebpagesCache::dispatch($product)->delay(15);
        }

        if (Arr::has($changed, 'image_id')) {
            ShopHydrateProductsWithNoImage::dispatch($product->shop)->delay(2);
        }

        ProductHydrateImages::run($product);
        UpdateProductWebImages::run($product);
    }
}
