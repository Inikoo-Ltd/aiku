<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:34:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Models\Catalogue\InAssetModel;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Ordering\ShippingZone
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $shipping_zone_schema_id
 * @property bool $status
 * @property string $slug
 * @property bool $is_failover
 * @property string $code
 * @property string $name
 * @property array<array-key, mixed> $price
 * @property array<array-key, mixed> $territories
 * @property int $position
 * @property int $currency_id
 * @property int|null $asset_id
 * @property int|null $current_historic_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Helpers\Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Ordering\ShippingZoneSchema $schema
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Ordering\ShippingZoneStats|null $stats
 * @method static \Database\Factories\Ordering\ShippingZoneFactory factory($count = null, $state = [])
 * @method static Builder<static>|ShippingZone newModelQuery()
 * @method static Builder<static>|ShippingZone newQuery()
 * @method static Builder<static>|ShippingZone onlyTrashed()
 * @method static Builder<static>|ShippingZone query()
 * @method static Builder<static>|ShippingZone withTrashed()
 * @method static Builder<static>|ShippingZone withoutTrashed()
 * @mixin Eloquent
 */
class ShippingZone extends Model implements Auditable
{
    use SoftDeletes;
    use InShop;
    use InAssetModel;
    use HasSlug;
    use HasFactory;
    use HasHistory;

    protected $casts = [
        'territories'     => 'array',
        'price'           => 'array',
        'status'          => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'territories' => '{}',
        'price'       => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['ordering'];
    }

    protected array $auditInclude = [
        'name',
        'type',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ShippingZoneStats::class);
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(ShippingZoneSchema::class, 'shipping_zone_schema_id');
    }


}
