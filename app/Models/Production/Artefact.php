<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 11:29:06 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\Stock;
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
 * @property int $production_id
 * @property int|null $stock_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $stock_family_id
 * @property ArtefactStateEnum $state
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\ManufactureTask> $manufactureTasks
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Production\Production $production
 * @property-read \App\Models\Production\ArtefactStats|null $stats
 * @property-read Stock|null $stock
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artefact withoutTrashed()
 * @mixin \Eloquent
 */
class Artefact extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use InProduction;
    use HasHistory;

    protected $casts = [
        'data'                   => 'array',
        'settings'               => 'array',
        'state'                  => ArtefactStateEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];


    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }


    public function stats(): HasOne
    {
        return $this->hasOne(ArtefactStats::class);
    }

    public function manufactureTasks()
    {
        return $this->belongsToMany(ManufactureTask::class, 'artefacts_manufacture_tasks');
    }


}
