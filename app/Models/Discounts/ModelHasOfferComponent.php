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
 * @property-read \App\Models\Discounts\OfferCampaign|null $offerCampaign
 * @property-read \App\Models\Discounts\OfferComponent|null $offerComponent
 * @method static Builder<static>|ModelHasOfferComponent newModelQuery()
 * @method static Builder<static>|ModelHasOfferComponent newQuery()
 * @method static Builder<static>|ModelHasOfferComponent query()
 * @mixin Eloquent
 */
class ModelHasOfferComponent extends Model
{
    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function offerComponent(): BelongsTo
    {
        return $this->belongsTo(OfferComponent::class);
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
