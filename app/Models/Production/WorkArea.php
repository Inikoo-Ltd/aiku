<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\HumanResources\Employee;
use App\Models\Traits\InProduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Where artisans physically work (Bath Bombs Area, Oils Area...). The product departments made there are linked to it.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ArtefactDepartment> $artefactDepartments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Employee> $artisans
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Production $production
 * @mixin \Eloquent
 */
class WorkArea extends Model
{
    use InProduction;

    protected $guarded = [];

    public function artisans(): MorphToMany
    {
        return $this->morphToMany(Employee::class, 'artisanable', 'artisan_assignments')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function artefactDepartments(): HasMany
    {
        return $this->hasMany(ArtefactDepartment::class);
    }
}
