<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 15:29:01 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\WebBlockType\WebBlockTypeScopeEnum;
use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property WebBlockTypeScopeEnum $scope
 * @property string $code
 * @property string $name
 * @property int|null $image_id
 * @property bool $fixed
 * @property string|null $description
 * @property array<array-key, mixed> $blueprint
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $category
 * @property array<array-key, mixed> $website_type
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $is_in_test
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Web\WebBlockTypeStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebBlock> $webBlocks
 * @method static Builder<static>|WebBlockType newModelQuery()
 * @method static Builder<static>|WebBlockType newQuery()
 * @method static Builder<static>|WebBlockType onlyTrashed()
 * @method static Builder<static>|WebBlockType query()
 * @method static Builder<static>|WebBlockType withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|WebBlockType withoutTrashed()
 * @mixin \Eloquent
 */
class WebBlockType extends Model implements HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use InGroup;
    use HasImage;

    protected $casts = [
        'blueprint' => 'array',
        'data'      => 'array',
        'scope'     => WebBlockTypeScopeEnum::class,
        'website_type' => 'array',
    ];

    protected $attributes = [
        'blueprint' => '{}',
        'data'      => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WebBlockTypeStats::class);
    }

    public function webBlocks(): HasMany
    {
        return $this->hasMany(WebBlock::class);
    }


}
