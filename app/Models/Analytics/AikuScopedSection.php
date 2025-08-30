<?php

/*
 * author Arya Permana - Kirin
 * created on 21-11-2024-10h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Analytics;

use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int $aiku_section_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $model_type
 * @property int $model_id
 * @property string $model_slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Analytics\AikuScopedSectionStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AikuScopedSection query()
 * @mixin \Eloquent
 */
class AikuScopedSection extends Model
{
    use HasSlug;
    use InOrganisation;

    protected $cast = [
        'code' => AikuSectionEnum::class
    ];

    protected $guarded = [
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code.'-'.$this->model->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(AikuScopedSectionStats::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }


}
