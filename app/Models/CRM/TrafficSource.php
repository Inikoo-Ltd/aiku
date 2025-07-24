<?php

namespace App\Models\CRM;

use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\TrafficSource\TrafficSource
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $type
 * @property string $slug
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\Customer> $customers
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\CRM\TrafficSourceStat|null $stats
 * @method static Builder<static>|TrafficSource newModelQuery()
 * @method static Builder<static>|TrafficSource newQuery()
 * @method static Builder<static>|TrafficSource query()
 * @mixin Eloquent
 */
class TrafficSource extends Model
{
    use InShop;
    use HasSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
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

}
