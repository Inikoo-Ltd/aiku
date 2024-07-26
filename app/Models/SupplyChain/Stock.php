<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 09:56:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\Stock\StockStateEnum;
use App\Enums\SupplyChain\Stock\StockTradeUnitCompositionEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Media;
use App\Models\Helpers\UniversalSearch;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\Stock
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $stock_family_id
 * @property StockTradeUnitCompositionEnum|null $trade_unit_composition
 * @property StockStateEnum $state
 * @property bool $sellable
 * @property bool $raw_material
 * @property int|null $units_per_pack units per pack
 * @property int|null $units_per_carton units per carton
 * @property string|null $unit_value
 * @property int|null $image_id
 * @property array $settings
 * @property array $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $discontinuing_at
 * @property Carbon|null $discontinued_at
 * @property Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property-read MediaCollection<int, Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Barcode> $barcode
 * @property-read Group $group
 * @property-read Media|null $image
 * @property-read MediaCollection<int, Media> $images
 * @property-read Collection<int, Location> $locations
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, OrgStock> $orgStocks
 * @property-read \App\Models\SupplyChain\StockStats|null $stats
 * @property-read \App\Models\SupplyChain\StockFamily|null $stockFamily
 * @property-read Collection<int, TradeUnit> $tradeUnits
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\SupplyChain\StockFactory factory($count = null, $state = [])
 * @method static Builder|Stock newModelQuery()
 * @method static Builder|Stock newQuery()
 * @method static Builder|Stock onlyTrashed()
 * @method static Builder|Stock query()
 * @method static Builder|Stock withTrashed()
 * @method static Builder|Stock withoutTrashed()
 * @mixin Eloquent
 */
class Stock extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasImage;
    use HasFactory;
    use HasAttachments;
    use HasHistory;

    protected $casts = [
        'data'                   => 'array',
        'settings'               => 'array',
        'activated_at'           => 'datetime',
        'discontinuing_at'       => 'datetime',
        'discontinued_at'        => 'datetime',
        'state'                  => StockStateEnum::class,
        'trade_unit_composition' => StockTradeUnitCompositionEnum::class,
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];


    public function generateTags(): array
    {
        return [
            'goods'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'state',
        'description',
        'trade_unit_composition',
        'sellable',
        'raw_material',
        'units_per_pack',
        'units_per_carton',
        'activated_at',
        'discontinuing_at',
        'discontinued_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }


    public function tradeUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            TradeUnit::class,
            'stock_trade_unit',
        )->withPivot(['quantity','notes'])->withTimestamps();
    }

    public function orgStocks(): HasMany
    {
        return $this->hasMany(OrgStock::class);
    }


    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->using(LocationOrgStock::class)->withTimestamps()
            ->withPivot('quantity');
    }



    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }



    public function stats(): HasOne
    {
        return $this->hasOne(StockStats::class);
    }

    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }


    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_stock')->withTimestamps()
            ->withPivot(['public','owner_type','owner_id'])
            ->wherePivot('type', 'image');
    }

    public function barcode(): MorphToMany
    {
        return $this->morphToMany(Barcode::class, 'mode', 'model_has_barcode')->withTimestamps();
    }
}
