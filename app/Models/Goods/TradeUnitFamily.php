<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:06:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\TradeUnit> $tradeUnits
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily withoutTrashed()
 * @mixin \Eloquent
 */
class TradeUnitFamily extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use InGroup;
    use HasHistory;
    use HasUniversalSearch;
    use HasFactory;

    protected $table = 'trade_unit_families';

    protected $casts = [
        'data'                        => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
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
        'description'
    ];

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

    public function tradeUnits(): HasMany
    {
        return $this->hasMany(TradeUnit::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TradeUnitFamilyStats::class);
    }
}
