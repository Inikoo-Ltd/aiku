<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_development_id
 * @property int|null $product_development_item_id
 * @property int|null $checklist_item_id
 * @property int|null $design_round_id
 * @property int|null $media_id
 * @property ProductDevelopmentFileScopeEnum $scope
 * @property int $version
 * @property Carbon|null $superseded_at
 * @property int|null $uploaded_by_id
 * @property string|null $model_type the sample or artwork a photo belongs to
 * @property int|null $model_id
 * @property-read ProductDevelopment|null $productDevelopment
 * @property-read ProductDevelopmentItem|null $item
 * @property-read Media|null $media
 * @property-read User|null $uploadedBy
 * @mixin \Eloquent
 */
class ProductDevelopmentFile extends Model
{
    protected $table = 'product_development_files';

    protected $casts = [
        'scope'         => ProductDevelopmentFileScopeEnum::class,
        'superseded_at' => 'datetime',
    ];

    protected $guarded = [];

    public function productDevelopment(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
