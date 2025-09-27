<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:56:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Billables;

use App\Actions\Utils\Abbreviate;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Billables\ShippingZoneSchema
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property ShippingZoneSchemaStateEnum $state
 * @property string $slug
 * @property string $name
 * @property bool $is_current
 * @property bool $is_current_discount
 * @property \Illuminate\Support\Carbon|null $live_at
 * @property \Illuminate\Support\Carbon|null $decommissioned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Order> $orders
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Collection<int, \App\Models\Billables\ShippingZone> $shippingZones
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Billables\ShippingZoneSchemaStats|null $stats
 * @method static \Database\Factories\Billables\ShippingZoneSchemaFactory factory($count = null, $state = [])
 * @method static Builder<static>|ShippingZoneSchema newModelQuery()
 * @method static Builder<static>|ShippingZoneSchema newQuery()
 * @method static Builder<static>|ShippingZoneSchema onlyTrashed()
 * @method static Builder<static>|ShippingZoneSchema query()
 * @method static Builder<static>|ShippingZoneSchema withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ShippingZoneSchema withoutTrashed()
 * @mixin Eloquent
 */
class ShippingZoneSchema extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InShop;
    use HasHistory;

    protected $casts = [
        'state'             => ShippingZoneSchemaStateEnum::class,
        'live_at'           => 'datetime',
        'decommissioned_at' => 'datetime',
        'fetched_at'        => 'datetime',
        'last_fetched_at'   => 'datetime',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['ordering'];
    }

    protected array $auditInclude = [
        'status',
        'code',
        'name',
        'territories',
        'price'

    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->name);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function shippingZones(): HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ShippingZoneSchemaStats::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

}
