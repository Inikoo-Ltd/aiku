<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 17:31:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $offer_campaign_id
 * @property int $number_offers
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_orders
 * @property string $amount
 * @property string $org_amount
 * @property string $group_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Discounts\OfferCampaign|null $asset
 * @method static \Illuminate\Database\Eloquent\Builder|OfferCampaignStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OfferCampaignStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OfferCampaignStats query()
 * @mixin \Eloquent
 */
class OfferCampaignStats extends Model
{
    protected $table = 'offer_campaign_stats';

    protected $guarded = [];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }
}
