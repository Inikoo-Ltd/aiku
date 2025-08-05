<?php

/*
 * author Arya Permana - Kirin
 * created on 28-10-2024-10h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Goods;

use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $name
 * @property array<array-key, mixed> $data
 * @property int $number_trade_units
 * @property int $number_stocks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property array<array-key, mixed> $source_data
 * @property array<array-key, mixed> $source_extra_ingredients
 * @property string|null $may_contain
 * @property string|null $ration
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\TradeUnit> $tradeUnits
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient query()
 * @mixin \Eloquent
 */
class Ingredient extends Model implements Auditable
{
    use HasSlug;
    use HasHistory;
    use InGroup;

    protected $guarded = [];


    protected $casts = [
        'data'                     => 'array',
        'sources'                  => 'array',
        'source_data'              => 'array',
        'source_extra_ingredients' => 'array',
        'fetched_at'               => 'datetime',
        'last_fetched_at'          => 'datetime',
    ];

    protected $attributes = [
        'sources'                  => '{}',
        'source_data'              => '{}',
        'source_extra_ingredients' => '{}',
        'data'                     => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'goods'
        ];
    }

    protected array $auditInclude = [
        'name'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }


    public function tradeUnits(): BelongsToMany
    {
        return $this->belongsToMany(TradeUnit::class, 'trade_unit_has_ingredients');
    }
}
