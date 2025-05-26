<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:43:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $image_id
 * @property int|null $shop_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string $parent_type
 * @property int $parent_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\CollectionCategory> $collectionCategories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $collections
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\ProductCategory> $departments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\ProductCategory> $families
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $inCollections
 * @property-read \App\Models\Catalogue\CollectionsOrderingStats|null $orderingStats
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\Product> $products
 * @property-read \App\Models\Catalogue\CollectionSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Catalogue\CollectionStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Webpage|null $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Collection withoutTrashed()
 * @mixin \Eloquent
 */
class Collection extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'data'  => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function stats(): HasOne
    {
        return $this->hasOne(CollectionStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(CollectionSalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(CollectionsOrderingStats::class);
    }

    public function collectionCategories(): BelongsToMany
    {
        return $this->belongsToMany(CollectionCategory::class);
    }

    public function inCollections(): MorphToMany
    {
        return $this->morphToMany(Collection::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function collections(): MorphToMany
    {
        return $this->morphedByMany(Collection::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'model', 'model_has_collections')->withTimestamps();
    }

    public function departments(): MorphToMany
    {
        return $this->morphedByMany(ProductCategory::class, 'model', 'model_has_collections')
                    ->wherePivot('type', 'Department')
                    ->withTimestamps();
    }
    public function families(): MorphToMany
    {
        return $this->morphedByMany(ProductCategory::class, 'model', 'model_has_collections')
                    ->wherePivot('type', 'Family')
                    ->withTimestamps();
    }

    public function webpage(): MorphOne
    {
        return $this->morphOne(Webpage::class, 'model');
    }
}
