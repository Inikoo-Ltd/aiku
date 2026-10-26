<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The current artwork of one SKU in one design section: an uploaded PDF or a layout built in Aiku.
 * It holds the latest version; every version sent for review is also kept in its versions.
 *
 * @property int $id
 * @property int $product_development_id
 * @property int $design_section_id
 * @property int $product_development_item_id
 * @property string|null $source upload or aiku
 * @property int|null $media_id
 * @property array<string, mixed> $layout
 * @property int $version
 * @property ProductDevelopmentArtworkStatusEnum $status
 * @property string|null $update_note
 * @property int|null $updated_by_id
 * @property Carbon|null $artwork_updated_at
 * @property string|null $review_comment
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $locked_at
 * @property string|null $approved_label_hash fingerprint of the SKU's label information when it was approved
 * @property string|null $packaging_sample_status approved or changes_required, set by the designer on the physical box or label
 * @property string|null $packaging_sample_note
 * @property int|null $packaging_sample_reviewed_by_id
 * @property Carbon|null $packaging_sample_reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopment|null $productDevelopment
 * @property-read ProductDevelopmentDesignSection|null $section
 * @property-read ProductDevelopmentItem|null $item
 * @property-read Media|null $media
 * @property-read User|null $updatedBy
 * @property-read User|null $reviewedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentArtworkVersion> $versions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentFile> $packagingSamplePhotos
 * @property-read User|null $packagingSampleReviewedBy
 * @mixin \Eloquent
 */
class ProductDevelopmentArtwork extends Model
{
    public const string SOURCE_UPLOAD = 'upload';

    public const string SOURCE_AIKU = 'aiku';

    public const string PACKAGING_SAMPLE_APPROVED = 'approved';

    public const string PACKAGING_SAMPLE_CHANGES_REQUIRED = 'changes_required';

    protected $table = 'product_development_artworks';

    protected $guarded = [];

    protected $attributes = [
        'layout' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'layout'             => 'array',
            'status'             => ProductDevelopmentArtworkStatusEnum::class,
            'artwork_updated_at' => 'datetime',
            'reviewed_at'        => 'datetime',
            'locked_at'          => 'datetime',
            'packaging_sample_reviewed_at' => 'datetime',
        ];
    }

    public function productDevelopment(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopment::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentDesignSection::class, 'design_section_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductDevelopmentArtworkVersion::class, 'artwork_id')->orderByDesc('version');
    }

    public function packagingSamplePhotos(): MorphMany
    {
        return $this->morphMany(ProductDevelopmentFile::class, 'model')->orderBy('id');
    }

    public function packagingSampleReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packaging_sample_reviewed_by_id');
    }
}
