<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceCampaignStat
 *
 * @property int $id
 * @property int $traffic_source_campaign_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric $number_customers
 * @property numeric $number_customer_purchases
 * @property numeric $total_customer_revenue
 * @property numeric $org_total_customer_revenue
 * @property numeric $total_cost
 * @property numeric $org_total_cost
 * @property-read \App\Models\CRM\TrafficSourceCampaign $trafficSourceCampaign
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignStat query()
 * @mixin \Eloquent
 */
class TrafficSourceCampaignStat extends Model
{
    use HasFactory;

    protected $table = 'traffic_source_campaign_stats';

    protected $guarded = [];

    public function trafficSourceCampaign(): BelongsTo
    {
        return $this->belongsTo(TrafficSourceCampaign::class);
    }
}
