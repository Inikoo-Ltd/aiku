<?php

/*
 * Author Louis Perez
 * Created on 26-06-2026-11h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $review_id
 * @property ReviewReactionTargetEnum $target Would be Enum (Review / Reply)
 * @property int $customer_id
 * @property ReviewReactionTypeEnum $type Would be Enum (Like / Dislike)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read \App\Models\Reviews\Review|null $review
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewReaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewReaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewReaction query()
 * @mixin \Eloquent
 */
class ReviewReaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target'    => ReviewReactionTargetEnum::class,
        'type'      => ReviewReactionTypeEnum::class,
    ];

    protected $attributes = [];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
