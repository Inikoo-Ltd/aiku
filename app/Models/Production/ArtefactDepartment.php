<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\Traits\HasHistory;
use App\Models\Traits\HasSearch;
use App\Models\Traits\InProduction;
use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $number_artefacts
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Artefact> $artefacts
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Production $production
 * @mixin \Eloquent
 */
class ArtefactDepartment extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasSearch;
    use InProduction;
    use HasHistory;

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'code',
        'name',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSearchableArray(): array
    {
        return [
            'id'              => (string)$this->id,
            'code'            => (string)$this->code,
            'name'            => (string)$this->name,
            'slug'            => (string)$this->slug,
            'production_id'   => $this->production_id,
            'organisation_id' => $this->organisation_id,
            'created_at'      => $this->created_at?->timestamp ?? 0,
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function artisans(): MorphToMany
    {
        return $this->morphToMany(Employee::class, 'artisanable', 'artisan_assignments')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function artefacts(): HasMany
    {
        return $this->hasMany(Artefact::class);
    }

    public function artefactFamilies(): HasMany
    {
        return $this->hasMany(ArtefactFamily::class);
    }
}
