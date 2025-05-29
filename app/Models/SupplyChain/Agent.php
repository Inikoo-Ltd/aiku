<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:31:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\Helpers\Currency;
use App\Models\Helpers\UniversalSearch;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\Agent
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $code mirror of organisation
 * @property string $name mirror of organisation
 * @property bool $status
 * @property string $slug
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency|null $currency
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, OrgAgent> $orgAgents
 * @property-read Collection<int, OrgSupplier> $orgSuppliers
 * @property-read Organisation $organisation
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\SupplyChain\AgentStats|null $stats
 * @property-read Collection<int, StockDelivery> $stockDeliveries
 * @property-read Collection<int, \App\Models\SupplyChain\SupplierProduct> $supplierProducts
 * @property-read Collection<int, \App\Models\SupplyChain\Supplier> $suppliers
 * @property-read Collection<int, \App\Models\SupplyChain\AgentTimeSeries> $timeSeries
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\SupplyChain\AgentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Agent newModelQuery()
 * @method static Builder<static>|Agent newQuery()
 * @method static Builder<static>|Agent onlyTrashed()
 * @method static Builder<static>|Agent query()
 * @method static Builder<static>|Agent withTrashed()
 * @method static Builder<static>|Agent withoutTrashed()
 * @mixin Eloquent
 */
class Agent extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use HasImage;
    use InGroup;

    protected $casts = [
        'status'          => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'sources'         => 'array',
    ];

    protected $attributes = [
        'sources' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'status',
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->organisation->slug;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    // Note: this a one-to-one relationship (agent is an extension of an organisation)
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(AgentStats::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockDeliveries(): HasMany
    {
        return $this->hasMany(StockDelivery::class);
    }

    public function orgAgents(): HasMany
    {
        return $this->hasMany(OrgAgent::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(AgentTimeSeries::class);
    }

}
