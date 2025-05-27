<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Helpers;

use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $name
 * @property TagScopeEnum $scope
 * @property array<array-key, mixed> $data
 * @property int $number_models
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @mixin \Eloquent
 */
class Tag extends Model
{
    use HasSlug;
    protected $guarded = [];

    protected $casts = [
        'data'     => 'array',
        'scope'    => TagScopeEnum::class,
    ];

    protected $attributes = [
        'data'     => '{}',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphedByMany(TradeUnit::class, 'model', 'model_has_tags');
    }
}
