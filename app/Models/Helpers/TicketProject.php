<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property TicketProjectStatusEnum $status
 * @property bool $is_pinned
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $target_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User|null $owner
 * @property-read TicketProject|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketProject> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketProjectUpdate> $updates
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketProjectMilestone> $milestones
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StaffTask> $staffTasks
 * @mixin \Eloquent
 */
class TicketProject extends Model implements HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use InGroup;
    use InteractsWithMedia;

    public const string FILES_COLLECTION = 'project_files';

    protected $guarded = [];

    protected $attributes = [
        'status' => TicketProjectStatusEnum::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'status'      => TicketProjectStatusEnum::class,
            'is_pinned'   => 'boolean',
            'start_date'  => 'date',
            'target_date' => 'date',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TicketProject::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TicketProject::class, 'parent_id')->orderBy('name');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_project_members')->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function staffTasks(): HasMany
    {
        return $this->hasMany(StaffTask::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(TicketProjectMilestone::class)->orderBy('position')->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TicketProjectUpdate::class);
    }

    public static function canBeCreatedBy(?User $user): bool
    {
        return $user !== null;
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null && (
            Ticket::canBeAssignedBy($user)
            || $this->owner_id === $user->id
            || $this->members()->whereKey($user->id)->exists()
        );
    }

    public function hasFile(Media $media): bool
    {
        return $media->model_type === $this->getMorphClass()
            && $media->model_id === $this->id
            && $media->collection_name === self::FILES_COLLECTION;
    }

    /**
     * @return array<int, array{ulid: string, name: string, size: int, mime: string|null, url: string, uploaded_by: string|null, created_at: mixed}>
     */
    public function projectFiles(): array
    {
        $files    = $this->getMedia(self::FILES_COLLECTION)->sortByDesc('id');
        $uploader = User::whereIn('id', $files->map(fn (Media $media) => $media->getCustomProperty('uploaded_by'))->filter()->unique())->get()->keyBy('id');

        return $files->map(fn (Media $media) => [
            'ulid'        => $media->ulid,
            'name'        => $media->name,
            'size'        => (int) $media->size,
            'mime'        => $media->mime_type,
            'url'         => route('grp.projects.files.show', ['ticketProject' => $this->slug, 'media' => $media->ulid]),
            'uploaded_by' => ($person = $uploader->get($media->getCustomProperty('uploaded_by'))) ? ($person->contact_name ?: $person->username) : null,
            'created_at'  => $media->created_at,
        ])->values()->all();
    }
}
