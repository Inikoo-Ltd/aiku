<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 14:36:10 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\WebBlockType\WebBlockTypeClassEnum;
use App\Enums\Web\WebBlockType\WebBlockTypeScopeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Web\WebBlockType
 *
 * @property int $id
 * @property WebBlockTypeScopeEnum $scope
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property WebBlockTypeClassEnum $class
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Web\WebBlockTypeStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebBlock> $webBlock
 * @method static Builder|WebBlockType newModelQuery()
 * @method static Builder|WebBlockType newQuery()
 * @method static Builder|WebBlockType onlyTrashed()
 * @method static Builder|WebBlockType query()
 * @method static Builder|WebBlockType withTrashed()
 * @method static Builder|WebBlockType withoutTrashed()
 * @mixin \Eloquent
 */
class WebBlockType extends Model
{
    use UsesLandlordConnection;
    use SoftDeletes;
    use HasSlug;

    protected $casts = [
        'data'  => 'array',
        'class' => WebBlockTypeClassEnum::class,
        'scope' => WebBlockTypeScopeEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function webBlock(): HasMany
    {
        return $this->hasMany(WebBlock::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WebBlockTypeStats::class);
    }

}
