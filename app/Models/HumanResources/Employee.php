<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 14:10:34 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\HumanResources;

use App\Audits\Redactors\EmployeePinRedactor;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\Miscellaneous\GenderEnum;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $alias
 * @property string|null $work_email
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property GenderEnum|null $gender
 * @property string|null $worker_number
 * @property string|null $job_title
 * @property EmployeeTypeEnum $type
 * @property EmployeeStateEnum $state
 * @property string|null $employment_start_at
 * @property string|null $employment_end_at
 * @property string|null $emergency_contact
 * @property array<array-key, mixed>|null $salary
 * @property array<array-key, mixed>|null $working_hours
 * @property numeric $week_working_hours
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $errors
 * @property string|null $pin
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property array<array-key, mixed> $migration_data
 * @property int|null $user_id
 * @property int|null $address_id
 * @property string|null $location
 * @property string|null $notes
 * @property-read \App\Models\Helpers\Address|null $address
 * @property-read Collection<int, \App\Models\Helpers\Address> $addresses
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, \App\Models\HumanResources\Clocking> $clockings
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\HumanResources\EmployeeHasJobPositions|null $pivot
 * @property-read Collection<int, \App\Models\HumanResources\JobPosition> $jobPositions
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\HumanResources\EmployeeStats|null $stats
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, \App\Models\HumanResources\TimeTracker> $timeTrackers
 * @property-read Collection<int, \App\Models\HumanResources\Timesheet> $timesheets
 * @property-read UniversalSearch|null $universalSearch
 * @property-read User|null $user
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, \App\Models\HumanResources\Workplace> $workplaces
 * @method static \Database\Factories\HumanResources\EmployeeFactory factory($count = null, $state = [])
 * @method static Builder<static>|Employee newModelQuery()
 * @method static Builder<static>|Employee newQuery()
 * @method static Builder<static>|Employee onlyTrashed()
 * @method static Builder<static>|Employee query()
 * @method static Builder<static>|Employee withTrashed()
 * @method static Builder<static>|Employee withoutTrashed()
 * @mixin Eloquent
 */
class Employee extends Model implements HasMedia, Auditable
{
    use HasSlug;
    use HasAddress;
    use HasAddresses;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasImage;
    use HasAttachments;
    use HasFactory;
    use HasHistory;
    use InOrganisation;

    protected $casts = [
        'week_working_hours' => 'decimal:2',
        'data'               => 'array',
        'errors'             => 'array',
        'salary'             => 'array',
        'working_hours'      => 'array',
        'migration_data'     => 'array',
        'date_of_birth'      => 'datetime:Y-m-d',
        'gender'             => GenderEnum::class,
        'state'              => EmployeeStateEnum::class,
        'type'               => EmployeeTypeEnum::class,
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',

    ];
    //ss

    protected $attributes = [
        'data'           => '{}',
        'errors'         => '{}',
        'salary'         => '{}',
        'working_hours'  => '{}',
        'migration_data' => '{}'
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['hr'];
    }

    protected array $auditInclude = [
        'alias',
        'work_email',
        'contact_name',
        'email',
        'phone',
        'identity_document_type',
        'identity_document_number',
        'date_of_birth',
        'gender',
        'worker_number',
        'job_title',
        'type',
        'state',
        'employment_start_at',
        'employment_end_at',
        'emergency_contact',
        'pin'
    ];

    protected array $attributeModifiers = [
        'pin' => EmployeePinRedactor::class,
    ];


    public static function boot(): void
    {
        parent::boot();

        if (app('app.scope') == 'han') {
            static::addGlobalScope('han', function ($builder) {
                /** @var ClockingMachine $clockingMachine */
                $clockingMachine = Auth::user();
                $builder->where('organisation_id', $clockingMachine->organisation_id);
            });
        }
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return head(explode(' ', trim($this->contact_name)));
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class, 'employee_has_job_positions')
            ->using(EmployeeHasJobPositions::class)->withTimestamps()->withPivot(['share', 'scopes']);
    }

    public function getUser(): ?User
    {
        return $this->morphToMany(User::class, 'model', 'user_has_models')->wherePivot('status', true)->withTimestamps()->first();
    }

    public function users(): MorphToMany
    {
        return $this->morphToMany(User::class, 'model', 'user_has_models')->withTimestamps()->withPivot('status');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function workplaces(): BelongsToMany
    {
        return $this->belongsToMany(Workplace::class)->withTimestamps();
    }

    public function stats(): HasOne
    {
        return $this->hasOne(EmployeeStats::class);
    }

    public function timesheets(): MorphMany
    {
        return $this->morphMany(Timesheet::class, 'subject');
    }

    public function timeTrackers(): MorphMany
    {
        return $this->morphMany(TimeTracker::class, 'subject');
    }

    public function clockings(): MorphMany
    {
        return $this->morphMany(Clocking::class, 'subject');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'assigner');
    }

}
