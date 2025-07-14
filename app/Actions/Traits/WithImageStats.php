<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 20:34:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait WithImageStats
{
    /**
     * Calculate image statistics for a model using DB queries
     *
     * @param Model $model The model to calculate image statistics for
     * @param string $modelType The type of the model (e.g., 'Product', 'TradeUnit')
     * @param bool $hasPublicImages Whether the model tracks public images
     * @param bool $useTotalImageSize Whether to use 'total_image_size' instead of 'images_size'
     * @return array The calculated statistics
     */
    protected function calculateImageStatsUsingDB(Model $model, string $modelType, bool $hasPublicImages = true, bool $useTotalImageSize = false): array
    {
        $query = DB::table('model_has_media')
            ->join('media', 'model_has_media.media_id', '=', 'media.id')
            ->where('model_has_media.model_id', $model->id)
            ->where('model_has_media.model_type', $modelType)
            ->select([
                DB::raw('COUNT(*) as number_images'),
                DB::raw('SUM(media.size) as total_size'),
                DB::raw('MAX(media.size) as max_size')
            ]);

        if ($hasPublicImages) {
            $query->addSelect([
                DB::raw('SUM(CASE WHEN model_has_media.is_public = true THEN 1 ELSE 0 END) as number_public_images'),
                DB::raw('SUM(CASE WHEN model_has_media.is_public = true THEN media.size ELSE 0 END) as public_size')
            ]);
        }

        $imageStats = $query->first();

        $stats = [
            'number_images' => $imageStats->number_images ?? 0,
            'average_image_size' => $imageStats->number_images > 0 ? ($imageStats->total_size / $imageStats->number_images) : 0,
            'max_image_size' => $imageStats->max_size ?? null
        ];

        // Use the appropriate field name based on the model type
        if ($useTotalImageSize) {
            $stats['total_image_size'] = $imageStats->total_size ?? 0;
        } else {
            $stats['images_size'] = $imageStats->total_size ?? 0;
        }

        if ($hasPublicImages) {
            $stats['number_public_images'] = $imageStats->number_public_images ?? 0;
            $stats['public_images_size'] = $imageStats->public_size ?? 0;
        }

        return $stats;
    }

    /**
     * Calculate image statistics for a model by loading all images
     *
     * @param Model $model The model to calculate image statistics for
     * @param bool $hasPublicImages Whether the model tracks public images
     * @param bool $useTotalImageSize Whether to use 'total_image_size' instead of 'images_size'
     * @return array The calculated statistics
     */
    protected function calculateImageStatsByLoading(Model $model, bool $hasPublicImages = true, bool $useTotalImageSize = false): array
    {
        // Get all images associated with the model
        $images = $model->images()->get();

        // Count the number of images
        $numberImages = $images->count();

        // Calculate total image size and max image size
        $totalImageSize = 0;
        $maxImageSize = 0;
        $publicImagesCount = 0;
        $publicImagesSize = 0;

        foreach ($images as $image) {
            $size = $image->size;
            $totalImageSize += $size;

            if ($size > $maxImageSize) {
                $maxImageSize = $size;
            }

            if ($hasPublicImages && $image->pivot->is_public) {
                $publicImagesCount++;
                $publicImagesSize += $size;
            }
        }

        // Calculate average image size
        $averageImageSize = $numberImages > 0 ? $totalImageSize / $numberImages : 0;

        $stats = [
            'number_images' => $numberImages,
            'average_image_size' => $averageImageSize,
            'max_image_size' => $maxImageSize > 0 ? $maxImageSize : null
        ];

        // Use the appropriate field name based on the model type
        if ($useTotalImageSize) {
            $stats['total_image_size'] = $totalImageSize;
        } else {
            $stats['images_size'] = $totalImageSize;
        }

        if ($hasPublicImages) {
            $stats['number_public_images'] = $publicImagesCount;
            $stats['public_images_size'] = $publicImagesSize;
        }

        return $stats;
    }
}
