<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a website's storefront HTML traffic did at the Cloudflare edge, from Cloudflare's GraphQL
 * Analytics. Cloudflare keeps those analytics for 31 days only, so the daily records are the last
 * copy: they are upserted, never deleted or rebuilt.
 *
 * @property int $id
 * @property int $website_id
 * @property TimeSeriesFrequencyEnum $frequency
 * @property string|null $from
 * @property string|null $to
 * @property array<array-key, mixed>|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebsiteEdgeTimeSeriesRecord> $records
 * @property-read \App\Models\Web\Website|null $website
 */
class WebsiteEdgeTimeSeries extends Model
{
    protected $table = 'website_edge_time_series';

    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'data'      => 'array',
            'frequency' => TimeSeriesFrequencyEnum::class,
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(WebsiteEdgeTimeSeriesRecord::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
