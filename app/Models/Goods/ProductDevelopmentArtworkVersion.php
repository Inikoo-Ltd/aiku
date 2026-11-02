<?php

/*
 * Author Louis Perez
 * Created on 23-10-2026-12h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a SKU's artwork as it was sent for review, with its file (uploads only) and how the
 * review went. The artwork itself only holds the latest version.
 *
 * @property int $id
 * @property int $artwork_id
 * @property int $version
 * @property string|null $source upload or aiku
 * @property int|null $media_id
 * @property ProductDevelopmentArtworkStatusEnum $status
 * @property string|null $update_note
 * @property int|null $submitted_by_id
 * @property Carbon|null $submitted_at
 * @property string|null $review_comment
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property string|null $sample_status approved or changes_required, the verdict on the sample made from this version
 * @property string|null $sample_note
 * @property int|null $sample_reviewed_by_id
 * @property Carbon|null $sample_reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopmentArtwork|null $artwork
 * @property-read Media|null $media
 * @property-read User|null $submittedBy
 * @property-read User|null $reviewedBy
 * @property-read User|null $sampleReviewedBy
 *
 * @mixin \Eloquent
 */
class ProductDevelopmentArtworkVersion extends Model
{
    protected $table = 'product_development_artwork_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'       => ProductDevelopmentArtworkStatusEnum::class,
            'submitted_at' => 'datetime',
            'reviewed_at'  => 'datetime',
            'sample_reviewed_at' => 'datetime',
        ];
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentArtwork::class, 'artwork_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function sampleReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sample_reviewed_by_id');
    }
}
