<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Models\Traits\HasHistory;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\CRM\TrafficSourceCampaign
 *
 * @property int $id
 * @property int $traffic_source_id
 * @property string $slug
 * @property string $reference
 * @property string $name
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $channel_type
 * @property array|null $data
 * @property-read \App\Models\CRM\TrafficSourceCampaignStat|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\TrafficSourceCampaignMetric> $metrics
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\TrafficSourceCampaignConversion> $conversions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\TrafficSourceCost> $costs
 * @property-read \App\Models\CRM\TrafficSource $trafficSource
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign query()
 * @mixin \Eloquent
 */
class TrafficSourceCampaign extends Model implements Auditable
{
    use HasFactory;
    use HasSlug;

    /* Changes pushed back to Google Ads spend real money, so who raised a budget or paused a
       campaign is recorded alongside every other audited model rather than in a log of its own. */
    use HasHistory;

    protected $guarded = [];

    /**
     * Nothing is audited automatically. The nightly Google Ads fetch rewrites `data` on every campaign
     * every night, if only to move `fetched_at`, which would bury the handful of entries that matter
     * under thousands saying a robot re-read the same numbers.
     *
     * Deliberate changes still record: a custom audit bypasses this list, which is the point of it.
     */
    protected array $auditEvents = [];

    protected $attributes = [
        'state' => GoogleAdsCampaignStateEnum::PUBLISHED_PAUSED->value,
    ];

    protected function casts(): array
    {
        return [
            'state' => GoogleAdsCampaignStateEnum::class,
            'data' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function trafficSource(): BelongsTo
    {
        return $this->belongsTo(TrafficSource::class, 'traffic_source_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TrafficSourceCampaignStat::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(TrafficSourceCampaignMetric::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(TrafficSourceCampaignConversion::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(TrafficSourceCost::class);
    }
}
