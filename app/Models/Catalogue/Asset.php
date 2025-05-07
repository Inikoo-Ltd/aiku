<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 02 Sept 2022 14:52:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\Space;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Transaction;
use App\Models\Traits\HasImage;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $master_asset_id
 * @property string $slug
 * @property bool $is_main
 * @property AssetTypeEnum $type
 * @property string $model_type
 * @property int|null $model_id
 * @property int|null $current_historic_asset_id
 * @property AssetStateEnum $state
 * @property bool $status
 * @property string $code mirror of asset model
 * @property string|null $name mirror of asset model
 * @property string|null $price mirror of asset model
 * @property numeric $units mirror of asset model
 * @property string|null $unit mirror of asset model
 * @property int $currency_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $follow_master
 * @property ProductUnitRelationshipType $unit_relationship_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Barcode> $barcode
 * @property-read Charge|null $charge
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceTransaction> $invoiceTransactions
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Model|\Eloquent|null $model
 * @property-read \App\Models\Catalogue\AssetOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Catalogue\AssetOrderingStats|null $orderingStats
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Product|null $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecurringBill> $recurringBills
 * @property-read Rental|null $rental
 * @property-read \App\Models\Catalogue\AssetSalesIntervals|null $salesIntervals
 * @property-read Service|null $service
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read Space|null $space
 * @property-read \App\Models\Catalogue\AssetStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\AssetTimeSeries> $timeSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Asset withoutTrashed()
 * @mixin \Eloquent
 */
class Asset extends Model implements HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InShop;
    use HasImage;

    protected $casts = [
        'units'                  => 'decimal:3',
        'data'                   => 'array',
        'status'                 => 'boolean',
        'type'                   => AssetTypeEnum::class,
        'state'                  => AssetStateEnum::class,
        'unit_relationship_type' => ProductUnitRelationshipType::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];


    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->shop->slug.'-'.$this->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(AssetStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(AssetSalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(AssetOrderingStats::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(AssetOrderingIntervals::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(AssetTimeSeries::class);
    }

    public function invoiceTransactions(): HasMany
    {
        return $this->hasMany(InvoiceTransaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }



    public function barcode(): MorphToMany
    {
        return $this->morphToMany(Barcode::class, 'model', 'model_has_barcodes')->withTimestamps();
    }

    public function historicAssets(): HasMany
    {
        return $this->hasMany(HistoricAsset::class);
    }

    public function historicAsset(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class, 'current_historic_asset_id');
    }

    public function service(): HasOne
    {
        return $this->hasOne(Service::class);
    }

    public function rental(): HasOne
    {
        return $this->hasOne(Rental::class);
    }

    public function product(): HasOne
    {
        return $this->hasOne(Product::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class);
    }

    public function space(): HasOne
    {
        return $this->hasOne(Space::class);
    }

    public function recurringBills(): MorphToMany
    {
        return $this->morphToMany(RecurringBill::class, 'model', 'model_has_recurring_bills')->withTimestamps();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }


}
