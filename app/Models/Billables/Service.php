<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:23:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Billables;

use App\Enums\Billables\Service\ServiceEditTypeEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Catalogue\InAssetModel;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Catalogue\Service
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $asset_id
 * @property bool $is_auto_assign
 * @property string|null $auto_assign_trigger What trigger this automation
 * @property string|null $auto_assign_subject Used for auto assign this service to a action
 * @property string|null $auto_assign_subject_type Used for auto assign this service to an action type
 * @property bool $auto_assign_status
 * @property bool $status
 * @property ServiceStateEnum $state
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property numeric|null $price
 * @property string $units
 * @property string $unit
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $currency_id
 * @property int|null $current_historic_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $historic_source_id
 * @property ServiceEditTypeEnum|null $edit_type
 * @property bool $is_public
 * @property bool $is_pallet_handling
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Helpers\Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecurringBill> $recurringBills
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Billables\ServiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service withoutTrashed()
 * @mixin \Eloquent
 */
class Service extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use InAssetModel;
    use HasHistory;
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'price'     => 'decimal:2',
        'state'     => ServiceStateEnum::class,
        'edit_type' => ServiceEditTypeEnum::class,
        'status'    => 'boolean',
        'data'      => 'array',
        'settings'  => 'array',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
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
        'barcode',
        'rrp',
        'unit_relationship_type'
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

    public function recurringBills(): MorphToMany
    {
        return $this->morphToMany(RecurringBill::class, 'model', 'model_has_recurring_bills')->withTimestamps();
    }

}
