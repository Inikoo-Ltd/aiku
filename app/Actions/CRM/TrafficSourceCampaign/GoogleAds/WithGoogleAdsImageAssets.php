<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait WithGoogleAdsImageAssets
{
    /**
     * Images are uploaded before the campaign is built, because Google's brand guidelines check
     * cannot see a logo created in the same request, and because an upload that fails should fail
     * before anything is committed.
     *
     * @return array<string, array<int, string>>
     */
    private function uploadImages(Shop $shop, array $modelData): array
    {
        $assets = [];

        foreach (['marketing_images', 'square_marketing_images', 'logos'] as $role) {
            foreach ((array) Arr::get($modelData, $role, []) as $mediaId) {
                $media = Media::find($mediaId);

                if (!$media) {
                    throw ValidationException::withMessages([$role => __('One of the images chosen no longer exists in Aiku.')]);
                }

                $assets[$role][] = UploadGoogleAdsImageAsset::run($shop, $media)['resource_name'];
            }
        }

        return $assets;
    }
}
