<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property-read \App\Models\CRM\TrafficSource $trafficSource
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrafficSourceCampaign query()
 * @mixin \Eloquent
 */
class TrafficSourceCampaign extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

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
}
