<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceStats
 *
 * @property int $id
 * @property int $traffic_source_id
 * @property numeric $number_customers
 * @property numeric $number_customer_purchases
 * @property numeric $total_customer_revenue
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric $total_cost
 * @property numeric $org_total_cost
 * @property numeric $org_total_customer_revenue
 * @property-read \App\Models\CRM\TrafficSource $trafficSource
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceStat query()
 * @mixin \Eloquent
 */
class TrafficSourceStat extends Model
{
    use HasFactory;

    protected $table = 'traffic_source_stats';

    protected $guarded = [];

    public function trafficSource(): BelongsTo
    {
        return $this->belongsTo(TrafficSource::class, 'traffic_source_id');
    }
}
