<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:04 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Models\Catalogue\Product;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Goods\TradeUnit
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $barcode_id
 * @property Barcode|null $barcode
 * @property int|null $net_weight (grams)
 * @property int|null $gross_weight incl packing (grams)
 * @property int|null $marketing_weight to be shown in website (grams)
 * @property array<array-key, mixed>|null $dimensions
 * @property float|null $volume in cubic meters
 * @property string|null $type unit type
 * @property int|null $image_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Barcode> $barcodes
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read Collection<int, \App\Models\Goods\Ingredient> $ingredients
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, Product> $outers
 * @property-read Collection<int, \App\Models\Goods\Stock> $stocks
 * @property-read Collection<int, SupplierProduct> $supplierProducts
 * @property-read Collection<int, Tag> $tags
 * @method static \Database\Factories\Goods\TradeUnitFactory factory($count = null, $state = [])
 * @method static Builder<static>|TradeUnit newModelQuery()
 * @method static Builder<static>|TradeUnit newQuery()
 * @method static Builder<static>|TradeUnit onlyTrashed()
 * @method static Builder<static>|TradeUnit query()
 * @method static Builder<static>|TradeUnit withTrashed()
 * @method static Builder<static>|TradeUnit withoutTrashed()
 * @mixin Eloquent
 */
class TradeUnit extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasImage;
    use HasFactory;
    use HasHistory;
    use HasAttachments;


    protected $casts = [
        'data'            => 'array',
        'dimensions'      => 'array',
        'sources'         => 'array',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'       => '{}',
        'dimensions' => '{}',
        'sources'    => '{}',
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
        'description',
        'barcode',
        'gross_weight',
        'net_weight',
        'dimensions',
        'volume',
        'type',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function stocks(): BelongsToMany
    {
        return $this->belongsToMany(Stock::class);
    }

    public function outers(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function supplierProducts(): MorphToMany
    {
        return $this->morphedByMany(SupplierProduct::class, 'model_has_trade_units');
    }

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function brands(): MorphToMany
    {
        return $this->morphToMany(Brand::class, 'model', 'model_has_brands');
    }

    public function brand(): ?Brand
    {
        return $this->brands()->first();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            Tag::class,
            'model',
            'model_has_tags'
        )->withTimestamps();
    }

    public function barcodes(): MorphToMany
    {
        return $this->morphToMany(Barcode::class, 'model', 'model_has_barcodes')
            ->withPivot('status', 'withdrawn_at', 'type')
            ->withTimestamps();
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'trade_unit_has_ingredients')->withTimestamps()
            ->withPivot(
                'prefix',
                'suffix',
                'notes',
                'concentration',
                'purity',
                'percentage',
                'aroma'
            );
    }


}
