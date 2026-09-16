<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\GoogleAdsMediaAsset
 *
 * @property int $id
 * @property int $shop_id
 * @property int $media_id
 * @property string $asset_resource_name
 * @property int|null $width
 * @property int|null $height
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Helpers\Media $media
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleAdsMediaAsset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleAdsMediaAsset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoogleAdsMediaAsset query()
 * @mixin \Eloquent
 */
class GoogleAdsMediaAsset extends Model
{
    protected $table = 'google_ads_media_assets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'width'  => 'integer',
            'height' => 'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
