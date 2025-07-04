<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 19:25:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        // Use DB statements to efficiently calculate image statistics
        $imageStats = DB::table('model_has_media')
            ->join('media', 'model_has_media.media_id', '=', 'media.id')
            ->where('model_has_media.model_id', $productCategory->id)
            ->where('model_has_media.model_type', 'ProductCategory')
            ->select([
                DB::raw('COUNT(*) as number_images'),
                DB::raw('SUM(media.size) as images_size'),
                DB::raw('SUM(CASE WHEN model_has_media.is_public = true THEN 1 ELSE 0 END) as number_public_images'),
                DB::raw('SUM(CASE WHEN model_has_media.is_public = true THEN media.size ELSE 0 END) as public_images_size')
            ])
            ->first();

        // Update product category stats
        $stats = [
            'number_images' => $imageStats->number_images ?? 0,
            'number_public_images' => $imageStats->number_public_images ?? 0,
            'images_size' => $imageStats->images_size ?? 0,
            'public_images_size' => $imageStats->public_images_size ?? 0,
        ];

        $productCategory->stats->update($stats);
    }
}
