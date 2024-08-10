<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 14:03:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\HumanResources;

use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Role;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $group_job_position_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property JobPositionScopeEnum $scope
 * @property string|null $department
 * @property string|null $team
 * @property array $data
 * @property bool $locked Seeded job positions should be locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\Employee> $employees
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\Employee> $employeesOtherOrganisations
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Guest> $guests
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read \App\Models\HumanResources\JobPositionStats|null $stats
 * @method static Builder|JobPosition newModelQuery()
 * @method static Builder|JobPosition newQuery()
 * @method static Builder|JobPosition query()
 * @mixin Eloquent
 */
class JobPosition extends Model implements Auditable
{
    use HasSlug;
    use HasHistory;
    use inOrganisation;


    protected $casts = [
        'data'  => 'array',
        'scope' => JobPositionScopeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'hr'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'team',
        'department',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(26);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_has_job_positions')
                    ->withPivot(['share', 'scopes'])
                    ->withTimestamps();
    }

    public function employeesOtherOrganisations(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_has_other_organisation_job_positions')
            ->withPivot(['share', 'scopes'])
            ->withTimestamps();
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'guest_has_job_positions')
            ->withPivot(['share', 'scopes'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function stats(): HasOne
    {
        return $this->hasOne(JobPositionStats::class);
    }
}
