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
 * @property int $id
 * @property int $offer_campaign_id
 * @property int $number_offers
 * @property int $number_current_offers
 * @property int $number_offers_state_in_process
 * @property int $number_offers_state_active
 * @property int $number_offers_state_finished
 * @property int $number_offers_state_suspended
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_orders
 * @property int $number_invoices
 * @property int $number_delivery_notes
 * @property string $amount
 * @property string $org_amount
 * @property string $grp_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Discounts\OfferCampaign|null $asset
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaignStats query()
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
