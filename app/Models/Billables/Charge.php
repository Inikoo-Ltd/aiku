<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:23:21 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Billables;

use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Models\Catalogue\InAssetModel;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $asset_id
 * @property bool $status
 * @property ChargeStateEnum $state
 * @property string $type
 * @property ChargeTriggerEnum $trigger
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $currency_id
 * @property int|null $current_historic_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Helpers\Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Billables\ChargeStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Charge withoutTrashed()
 * @mixin \Eloquent
 */
class Charge extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use InAssetModel;
    use HasHistory;
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    protected $casts = [
        'state'    => ChargeStateEnum::class,
        'trigger'  => ChargeTriggerEnum::class,
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
            'catalogue',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
        'price',
        'currency_id',
        'units',
        'unit',
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

    public function stats(): HasOne
    {
        return $this->hasOne(ChargeStats::class);
    }

}
