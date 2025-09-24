<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:30:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SysAdmin\Guest
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property bool $status
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $gender
 * @property array<array-key, mixed> $data
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property int|null $user_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Clocking> $clockings
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\SysAdmin\GuestStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TimeTracker> $timeTrackers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Timesheet> $timesheets
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read \App\Models\SysAdmin\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\User> $users
 * @method static \Database\Factories\SysAdmin\GuestFactory factory($count = null, $state = [])
 * @method static Builder<static>|Guest newModelQuery()
 * @method static Builder<static>|Guest newQuery()
 * @method static Builder<static>|Guest onlyTrashed()
 * @method static Builder<static>|Guest query()
 * @method static Builder<static>|Guest withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Guest withoutTrashed()
 * @mixin Eloquent
 */
class Guest extends Model implements HasMedia, Auditable
{
    use HasSlug;
    use InteractsWithMedia;
    use SoftDeletes;
    use HasFactory;
    use HasHistory;
    use HasUniversalSearch;


    protected $casts = [
        'data'          => 'array',
        'date_of_birth' => 'datetime:Y-m-d',
        'status'        => 'boolean',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'sysadmin'
        ];
    }

    protected array $auditInclude = [
        'code',
        'status',
        'contact_name',
        'company_name',
        'email',
        'phone',
        'identity_document_type',
        'identity_document_number',
        'date_of_birth',
        'gender',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(256)
                    ->height(256);
            });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(GuestStats::class);
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

}
