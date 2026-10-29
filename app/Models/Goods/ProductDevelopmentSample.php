<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-13h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Production\JobOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One sample of an in-house SKU, numbered per SKU (01, 02…). Once it is ready for review its recipe is
 * copied in, so a later change to the recipe never rewrites what this sample was made from.
 *
 * @property int $id
 * @property int $product_development_id
 * @property int $product_development_item_id
 * @property int $number
 * @property ProductDevelopmentSampleStatusEnum $status
 * @property array<string, mixed>|null $recipe the recipe it was made from: steps and ingredients with percentages
 * @property string|null $batch_size
 * @property string|null $batch_unit
 * @property string|null $notes
 * @property int|null $job_order_id the trial run it is made in
 * @property int|null $made_by_id
 * @property Carbon|null $ready_at
 * @property string|null $review_comment
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopment|null $productDevelopment
 * @property-read ProductDevelopmentItem|null $item
 * @property-read JobOrder|null $jobOrder
 * @property-read User|null $madeBy
 * @property-read User|null $reviewedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentFile> $photos
 *
 * @mixin \Eloquent
 */
class ProductDevelopmentSample extends Model
{
    protected $table = 'product_development_samples';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'      => ProductDevelopmentSampleStatusEnum::class,
            'recipe'      => 'array',
            'ready_at'    => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function productDevelopment(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function madeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(ProductDevelopmentFile::class, 'model')->orderBy('id');
    }

    public function reference(): string
    {
        return str_pad((string) $this->number, 2, '0', STR_PAD_LEFT);
    }
}
