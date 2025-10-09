<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 10:14:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InProduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Class ManufactureTask
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $task_materials_cost
 * @property string $task_energy_cost
 * @property string $task_other_cost
 * @property string $task_work_cost
 * @property bool $status
 * @property float $task_lower_target
 * @property float $task_upper_target
 * @property ManufactureTaskOperativeRewardTermsEnum $operative_reward_terms
 * @property ManufactureTaskOperativeRewardAllowanceTypeEnum $operative_reward_allowance_type
 * @property float $operative_reward_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\Artefact> $artefacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production $production
 * @property-read \App\Models\Production\ManufactureTaskStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTask withoutTrashed()
 * @mixin \Eloquent
 */

class ManufactureTask extends Model implements Auditable
{
    use InProduction;
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasHistory;

    protected $guarded = [];

    protected $casts   = [
        'data'                                => 'array',
        'operative_reward_terms'              => ManufactureTaskOperativeRewardTermsEnum::class,
        'operative_reward_allowance_type'     => ManufactureTaskOperativeRewardAllowanceTypeEnum::class,
    ];

    protected $attributes = [
        'data'     => '{}',
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
        return $this->hasOne(ManufactureTaskStats::class);
    }

    public function artefacts(): BelongsToMany
    {
        return $this->belongsToMany(Artefact::class, 'artefacts_manufacture_tasks');
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
