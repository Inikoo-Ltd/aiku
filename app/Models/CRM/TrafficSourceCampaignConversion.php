<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceCampaignConversion
 *
 * @property int $id
 * @property int $traffic_source_campaign_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $category
 * @property string $action_name
 * @property string $conversions
 * @property string $source_conversions_value
 * @property string $all_conversions
 * @property string $source_all_conversions_value
 * @property int|null $source_currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\TrafficSourceCampaign $trafficSourceCampaign
 * @property-read \App\Models\Helpers\Currency|null $sourceCurrency
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignConversion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignConversion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaignConversion query()
 * @mixin \Eloquent
 */
class TrafficSourceCampaignConversion extends Model
{
    public const string CATEGORY_PURCHASE = 'PURCHASE';

    public const string CATEGORY_SIGNUP = 'SIGNUP';

    protected $table = 'traffic_source_campaign_conversions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                         => 'date',
            'conversions'                  => 'decimal:2',
            'source_conversions_value'     => 'decimal:2',
            'all_conversions'              => 'decimal:2',
            'source_all_conversions_value' => 'decimal:2',
        ];
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
