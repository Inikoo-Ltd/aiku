<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 20:58:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InProduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property RawMaterialTypeEnum $type
 * @property RawMaterialStateEnum $state
 * @property int $production_id
 * @property int|null $stock_id
 * @property string $code
 * @property string $description
 * @property RawMaterialUnitEnum $unit
 * @property string $unit_cost
 * @property string|null $quantity_on_location
 * @property RawMaterialStockStatusEnum $stock_status
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production $production
 * @property-read \App\Models\Production\RawMaterialStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RawMaterial withoutTrashed()
 * @mixin \Eloquent
 */
class RawMaterial extends Model implements Auditable
{
    use InProduction;
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'data'         => 'array',
        'type'         => RawMaterialTypeEnum::class,
        'state'        => RawMaterialStateEnum::class,
        'unit'         => RawMaterialUnitEnum::class,
        'stock_status' => RawMaterialStockStatusEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(RawMaterialStats::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

}
