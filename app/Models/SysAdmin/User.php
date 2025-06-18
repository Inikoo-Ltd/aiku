<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:30:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Actions\SysAdmin\User\SendLinkResetPassword;
use App\Audits\Redactors\PasswordRedactor;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\Analytics\UserRequest;
use App\Models\Catalogue\Shop;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\Fulfilment\Fulfilment;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\Traits\HasEmail;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasRoles;
use App\Models\Traits\IsUserable;
use App\Models\Traits\WithPushNotifications;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property bool $status
 * @property string $username
 * @property string|null $password
 * @property UserAuthTypeEnum $auth_type
 * @property string|null $contact_name no-normalised depends on parent
 * @property string|null $email
 * @property string|null $about
 * @property int $number_models
 * @property int $number_active_models
 * @property int $number_employees
 * @property int $number_active_employees
 * @property int $number_guests
 * @property int $number_active_guests
 * @property int $number_authorised_organisations
 * @property int $number_authorised_shops
 * @property int $number_authorised_fulfilments
 * @property int $number_authorised_warehouses
 * @property int $number_authorised_productions
 * @property string|null $remember_token
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property bool $reset_password
 * @property int $language_id
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property string|null $legacy_password source password
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Organisation> $authorisedAgentsOrganisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Organisation> $authorisedDigitalAgencyOrganisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Fulfilment> $authorisedFulfilments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Organisation> $authorisedOrganisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Production> $authorisedProductions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Organisation> $authorisedShopOrganisations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Shop> $authorisedShops
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Warehouse> $authorisedWarehouses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Employee> $employees
 * @property-read \App\Models\Notifications\FcmToken|null $fcmToken
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notifications\FcmToken> $fcmTokens
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Guest> $guests
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Helpers\Language $language
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read \App\Models\SysAdmin\UserHasPseudoJobPositions|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JobPosition> $pseudoJobPositions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\SysAdmin\UserStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OutBoxHasSubscriber> $subscribedOutboxes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\Task> $tasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\UserTimeSeries> $timeSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SysAdmin\UserHasAuthorisedModels> $userAuthorisedModels
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserRequest> $userRequests
 * @method static \Database\Factories\SysAdmin\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia, Auditable
{
    use HasEmail;
    use HasRoles;
    use WithPushNotifications;
    use IsUserable;
    use HasImage;
    use HasApiTokens;

    protected $guarded = [
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'data'      => 'array',
        'settings'  => 'array',
        'sources'   => 'array',
        'status'    => 'boolean',
        'auth_type' => UserAuthTypeEnum::class,
        'password'  => 'hashed',
    ];


    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'sources'  => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'sysadmin'
        ];
    }

    protected array $auditInclude = [
        'status',
        'username',
        'password',
        'type',
        'auth_type',
        'contact_name',
        'email',
        'about',
        'language_id'
    ];

    protected array $attributeModifiers = [
        'password' => PasswordRedactor::class,
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return head(explode('@', trim($this->username)));
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }


    public function sendPasswordResetNotification($token): void
    {
        SendLinkResetPassword::run($token, $this);
    }

    public function routeNotificationForFcm(): array
    {
        return $this->fcmTokens->pluck('fcm_token')->toArray();
    }


    public function employees(): MorphToMany
    {
        return $this->morphedByMany(Employee::class, 'model', 'user_has_models')->withTimestamps();
    }

    public function guests(): MorphToMany
    {
        return $this->morphedByMany(Guest::class, 'model', 'user_has_models')->withTimestamps();
    }


    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }


    public function stats(): HasOne
    {
        return $this->hasOne(UserStats::class);
    }

    public function userRequests(): HasMany
    {
        return $this->hasMany(UserRequest::class);
    }


    public function userAuthorisedModels(): HasMany
    {
        return $this->hasMany(UserHasAuthorisedModels::class);
    }

    public function authorisedOrganisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'model', 'user_has_authorised_models')->withTimestamps();
    }

    public function authorisedShopOrganisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'model', 'user_has_authorised_models')->where('organisations.type', OrganisationTypeEnum::SHOP)->withTimestamps();
    }

    public function authorisedAgentsOrganisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'model', 'user_has_authorised_models')->where('organisations.type', OrganisationTypeEnum::AGENT)->withTimestamps();
    }

    public function authorisedDigitalAgencyOrganisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'model', 'user_has_authorised_models')->where('organisations.type', OrganisationTypeEnum::DIGITAL_AGENCY)->withTimestamps();
    }

    public function authorisedShops(): MorphToMany
    {
        return $this->morphedByMany(Shop::class, 'model', 'user_has_authorised_models')->withTimestamps();
    }

    public function authorisedFulfilments(): MorphToMany
    {
        return $this->morphedByMany(Fulfilment::class, 'model', 'user_has_authorised_models')->withTimestamps();
    }

    public function authorisedWarehouses(): MorphToMany
    {
        return $this->morphedByMany(Warehouse::class, 'model', 'user_has_authorised_models')->withTimestamps();
    }

    public function authorisedProductions(): MorphToMany
    {
        return $this->morphedByMany(Production::class, 'model', 'user_has_authorised_models')->withTimestamps();
    }

    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'taskable', 'users_has_tasks');
    }

    public function pseudoJobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class, 'user_has_pseudo_job_positions')->withTimestamps()
            ->using(UserHasPseudoJobPositions::class)->withPivot(['scopes']);
    }

    /*
     * Get all the job positions of the user from the employees (real job positions)
     */
    public function getJobPositions(): \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        return $this->employees->map(function ($employee) {
            return $employee->jobPositions;
        })->flatten();
    }

    /*
     * Get all the organisations where user is an employee
     */
    public function getOrganisations(): Collection
    {

        return $this->employees->map(function ($employee) {
            return $employee->organisation;
        })->flatten();
    }

    public function getOrganisation(): ?Organisation
    {
        return $this->getOrganisations()->first();
    }


    public function timeSeries(): HasMany
    {
        return $this->hasMany(UserTimeSeries::class);
    }

    public function subscribedOutboxes(): HasMany
    {
        return $this->hasMany(OutBoxHasSubscriber::class);
    }


}
