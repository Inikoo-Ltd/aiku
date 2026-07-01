<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 15:17:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Models\Catalogue\Asset;
use App\Models\Goods\ModelHasBarcode;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Helpers\Barcode
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $type
 * @property BarcodeStatusEnum $status
 * @property string $number
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Collection<int, ModelHasBarcode> $modelHasBarcodes
 * @property-read Collection<int, Asset> $products
 * @property-read Collection<int, Stock> $stocks
 * @property-read Collection<int, TradeUnit> $tradeUnits
 * @property-read Collection<int, TradeUnit> $tradeUnitsActive
 * @method static Builder<static>|Barcode newModelQuery()
 * @method static Builder<static>|Barcode newQuery()
 * @method static Builder<static>|Barcode onlyTrashed()
 * @method static Builder<static>|Barcode query()
 * @method static Builder<static>|Barcode withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Barcode withoutTrashed()
 * @mixin Eloquent
 */
class Barcode extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use InGroup;
    use HasHistory;

    protected $casts = [
        'data'                        => 'array',
        'fetched_at'                  => 'datetime',
        'last_fetched_at'             => 'datetime',
        'created_at'                  => 'datetime',
        'assigned_at'                 => 'datetime',
        'status'                      => BarcodeStatusEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'helpers',
        ];
    }

    protected array $auditInclude = [
        'number',
        'note',
        'assigned_at',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('number')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Uneeded, we didn't use it anyway.
    public function stocks(): MorphToMany
    {
        return $this->morphedByMany(Stock::class, 'model', 'model_has_barcodes');
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphedByMany(TradeUnit::class, 'model', 'model_has_barcodes');
    }

    // Uneeded, we didn't use it anyway.
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Asset::class, 'model', 'model_has_barcodes');
    }

    public function tradeUnitsActive(): MorphToMany
    {
        return $this->morphedByMany(TradeUnit::class, 'model', 'model_has_barcodes')->where('model_has_barcodes.status', true);
    }

    public function modelHasBarcodes(): HasMany
    {
        return $this->hasMany(ModelHasBarcode::class, 'barcode_id', 'id');
    }
}
