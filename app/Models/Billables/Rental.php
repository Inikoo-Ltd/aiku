<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:24:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Billables;

use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\InAssetModel;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Catalogue\Rental
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $fulfilment_id
 * @property int|null $asset_id
 * @property string|null $auto_assign_asset Used for auto assign this rent to this asset
 * @property string|null $auto_assign_asset_type Used for auto assign this rent to this asset type
 * @property bool $status
 * @property RentalStateEnum $state
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property numeric|null $price
 * @property string $units
 * @property RentalUnitEnum $unit
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $currency_id
 * @property int|null $current_historic_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $historic_source_id
 * @property RentalTypeEnum $type
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Helpers\Currency $currency
 * @property-read Fulfilment|null $fulfilment
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HistoricAsset> $historicAssets
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rental withoutTrashed()
 * @mixin \Eloquent
 */
class Rental extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use InAssetModel;
    use HasHistory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'price'    => 'decimal:2',
        'state'    => RentalStateEnum::class,
        'type'     => RentalTypeEnum::class,
        'unit'     => RentalUnitEnum::class,
        'status'   => 'boolean',
        'data'     => 'array',
        'settings' => 'array',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue','fulfilment'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
        'status',
        'state',
        'price',
        'currency_id',
        'units',
        'unit',
        'is_auto_assign',
        'auto_assign_asset',
        'auto_assign_asset_type',
    ];


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


    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }


}
