<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-10h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Analytics;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Fulfilment> $fulfilments
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Group> $groups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Organisation> $organisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Production> $productions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Shop> $shops
 * @property-read \App\Models\Analytics\AikuSectionStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Warehouse> $warehouses
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSection dSections()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuSection query()
 * @mixin \Eloquent
 */
class AikuSection extends Model
{
    use InGroup;
    use HasSlug;

    protected $guarded = [
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

    public function stats(): HasOne
    {
        return $this->hasOne(AikuSectionStats::class);
    }

    public function scopedSections(): HasMany
    {
        return $this->hasMany(AikuScopedSection::class);
    }


    public function shops(): MorphToMany
    {
        return $this->morphedByMany(Shop::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }

    public function fulfilments(): MorphToMany
    {
        return $this->morphedByMany(Fulfilment::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }

    public function warehouses(): MorphToMany
    {
        return $this->morphedByMany(Warehouse::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }

    public function productions(): MorphToMany
    {
        return $this->morphedByMany(Production::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }

    public function organisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }

    public function groups(): MorphToMany
    {
        return $this->morphedByMany(Group::class, 'model', 'aiku_section_has_models')->withTimestamps();
    }
}
