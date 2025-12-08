<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-11h-08m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Discounts;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\Discounts\Offer|null $offer
 * @property-read \App\Models\Discounts\OfferAllowance|null $offerAllowance
 * @property-read \App\Models\Discounts\OfferCampaign|null $offerCampaign
 *
 * @method static Builder<static>|ModelHasOfferAllowance newModelQuery()
 * @method static Builder<static>|ModelHasOfferAllowance newQuery()
 * @method static Builder<static>|ModelHasOfferAllowance query()
 *
 * @mixin Eloquent
 */
class ModelHasOfferAllowance extends Model
{
    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function offerAllowance(): BelongsTo
    {
        return $this->belongsTo(OfferAllowance::class);
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
