<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Models\Helpers\Currency;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceCost
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $traffic_source_id
 * @property int|null $traffic_source_campaign_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $amount
 * @property string $org_amount
 * @property string $source_amount
 * @property int $source_currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TrafficSource $trafficSource
 * @property-read TrafficSourceCampaign|null $trafficSourceCampaign
 * @property-read Currency $sourceCurrency
 * @mixin \Eloquent
 */
class TrafficSourceCost extends Model
{
    use HasFactory;
    use InShop;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'amount'        => 'decimal:2',
            'org_amount'    => 'decimal:2',
            'source_amount' => 'decimal:2',
        ];
    }

    public function trafficSource(): BelongsTo
    {
        return $this->belongsTo(TrafficSource::class);
    }

    public function trafficSourceCampaign(): BelongsTo
    {
        return $this->belongsTo(TrafficSourceCampaign::class);
    }

    public function sourceCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'source_currency_id');
    }
}
