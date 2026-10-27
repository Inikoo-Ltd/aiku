<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTypeEnum;
use App\Models\Helpers\Brand;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\Production\Production;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property int $number
 * @property string $code
 * @property string $name
 * @property ProductDevelopmentKindEnum $kind a sourced product, or a recipe made in our own production
 * @property int|null $production_id the production unit making an in-house recipe
 * @property ProductDevelopmentTypeEnum $type
 * @property ProductDevelopmentStatusEnum $status
 * @property int $current_position
 * @property string|null $checklist_category
 * @property int|null $owner_id
 * @property int|null $trade_unit_family_id
 * @property int|null $packaging_family_id
 * @property int|null $supplier_id
 * @property int|null $agent_id
 * @property Carbon|null $target_landing_date
 * @property Carbon|null $target_online_date
 * @property string|null $brief
 * @property array<array-key, mixed> $markets
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductDevelopmentStats|null $stats
 * @property-read User|null $owner
 * @property-read TradeUnitFamily|null $tradeUnitFamily
 * @property-read PackagingFamily|null $packagingFamily
 * @property-read Supplier|null $supplier
 * @property-read Agent|null $agent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentMember> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentStage> $stages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentDesignSection> $designSections
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentArtwork> $artworks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentChecklistItem> $checklistItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Brand> $brands
 * @mixin \Eloquent
 */
class ProductDevelopment extends Model implements Auditable, HasMedia
{
    use InteractsWithMedia;
    use HasSlug;
    use SoftDeletes;
    use InGroup;
    use HasHistory;
    use HasFactory;

    protected $table = 'product_developments';

    protected $casts = [
        'kind'                => ProductDevelopmentKindEnum::class,
        'type'                => ProductDevelopmentTypeEnum::class,
        'status'              => ProductDevelopmentStatusEnum::class,
        'target_landing_date' => 'date',
        'target_online_date'  => 'date',
        'markets'             => 'array',
        'data'                => 'array',
    ];

    protected $attributes = [
        'markets' => '[]',
        'data'    => '{}',
    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'name',
        'kind',
        'type',
        'status',
        'current_position',
        'checklist_category',
        'owner_id',
        'trade_unit_family_id',
        'packaging_family_id',
        'supplier_id',
        'agent_id',
        'production_id',
        'target_landing_date',
        'target_online_date',
        'brief',
        'markets',
    ];

    public function generateTags(): array
    {
        return [
            'goods',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ProductDevelopmentStats::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class);
    }

    public function packagingFamily(): BelongsTo
    {
        return $this->belongsTo(PackagingFamily::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductDevelopmentComponent::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(ProductDevelopmentSample::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProductDevelopmentMember::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProductDevelopmentStage::class)->orderBy('position')->orderBy('sub_position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductDevelopmentItem::class)->orderBy('position');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ProductDevelopmentChecklistItem::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductDevelopmentFile::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductDevelopmentComment::class);
    }

    public function designRounds(): HasMany
    {
        return $this->hasMany(ProductDevelopmentDesignRound::class);
    }

    public function designSections(): HasMany
    {
        return $this->hasMany(ProductDevelopmentDesignSection::class)->orderBy('position')->orderBy('id');
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(ProductDevelopmentArtwork::class);
    }

    public function brands(): MorphToMany
    {
        return $this->morphToMany(Brand::class, 'model', 'model_has_brands');
    }

    public function brand(): ?Brand
    {
        /** @var Brand|null $brand */
        $brand = $this->brands()->first();

        return $brand;
    }

    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'product_development_purchase_order')->withTimestamps();
    }

    /**
     * Purchase orders linked by purchasing to a component on the buyer's list; pivot trade_unit_id says which.
     */
    public function componentPurchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'product_development_component_purchase_orders')->withPivot(['trade_unit_id', 'linked_by_id'])->withTimestamps();
    }

    public function tradeUnits(): BelongsToMany
    {
        return $this->belongsToMany(TradeUnit::class, 'product_development_items')->withPivot('supplier_product_id', 'position')->withTimestamps();
    }
}
