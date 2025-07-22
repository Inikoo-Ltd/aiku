<?php

namespace App\Models\CRM;

use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\TrafficSource\TrafficSource
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $shop_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder|TrafficSource newModelQuery()
 * @method static Builder|TrafficSource newQuery()
 * @method static Builder|TrafficSource query()
 * @mixin Eloquent
 */
class TrafficSource extends Model
{
    use HasSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $attributes = [
        'settings' => '{}',
    ];

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

    public function stats(): HasOne
    {
        return $this->hasOne(TrafficSourceStat::class, 'traffic_source_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'traffic_source_id');
    }

    public static function detectFromWebsite(Website $website, string $url): ?self
    {
        $url = strtolower($url);

        // Get all traffic sources and check their patterns
        $trafficSources = self::where('group_id', $website->group_id)
            ->where('shop_id', $website->shop_id)
            ->where('organisation_id', $website->organisation_id)
            ->get();

        foreach ($trafficSources as $trafficSource) {
            $patterns = $trafficSource->settings['url_patterns'] ?? [];

            foreach ($patterns as $pattern) {
                if (str_contains($url, strtolower($pattern))) {
                    return $trafficSource;
                }
            }
        }

        return null;
    }
}
