<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Tasks;

use App\Models\Traits\InGroup;
use App\Models\Traits\InTicketProject;
use App\Models\Chat\StaffConversation;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasTicketImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $ticket_project_id
 * @property int|null $ticket_project_milestone_id
 * @property int $number
 * @property string $reference
 * @property string $subject
 * @property string|null $description
 * @property int $requester_id
 * @property string|null $department
 * @property int|null $assignee_id
 * @property StaffTaskStatusEnum $status
 * @property ChatPriorityEnum $priority
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property string|null $model_type
 * @property int|null $model_id
 * @property int|null $staff_conversation_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property-read User $requester
 * @property-read User|null $assignee
 * @property-read StaffConversation|null $conversation
 * @property-read Model|null $model
 * @mixin \Eloquent
 */
class StaffTask extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasHistory;
    use InteractsWithMedia;
    use HasTicketImages;
    use InGroup;
    use InTicketProject;

    public const array LINKABLE_MODELS = ['Product', 'Customer', 'Order', 'DeliveryNote', 'Location', 'OrgStock', 'ChatSession', 'MetaChatSession'];

    public const array PEOPLE_SCOPED_MODELS = ['Product', 'Customer', 'Order', 'DeliveryNote'];

    public const array SUBTASK_STATUSES = ['todo', 'in_progress', 'done'];

    /**
     * Mirrors the authorisation of the linked record's own page, so the people offered for a task are the ones who can open it.
     *
     * @return string[]
     */
    public static function viewPermissionsOf(string $modelType, int $modelId, int $groupId): array
    {
        $record = Relation::getMorphedModel($modelType)::query()->where('group_id', $groupId)->findOrFail($modelId);

        return match ($modelType) {
            'Customer'     => ["crm.$record->shop_id.view", "accounting.$record->organisation_id.view"],
            'Order'        => ["orders.$record->shop_id.view", "accounting.$record->organisation_id.view"],
            'Product'      => ["products.$record->shop_id.view", "web.$record->shop_id.view", 'group-webmaster.view', "accounting.$record->organisation_id.view"],
            'DeliveryNote' => ["dispatching.$record->warehouse_id.view", "fulfilment.$record->warehouse_id.view"],
        };
    }

    protected $guarded = [];

    protected $attributes = [
        'data'     => '{}',
        'status'   => StaffTaskStatusEnum::TODO,
        'priority' => ChatPriorityEnum::NORMAL,
    ];

    protected array $auditInclude = ['status', 'assignee_id', 'department', 'priority', 'due_at', 'subject', 'description', 'ticket_project_id'];

    protected function casts(): array
    {
        return [
            'status'      => StaffTaskStatusEnum::class,
            'priority'    => ChatPriorityEnum::class,
            'data'        => 'array',
            'due_at'      => 'date',
            'assigned_at' => 'datetime',
            'started_at'  => 'datetime',
            'closed_at'   => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_task_collaborators')->withPivot('added_by_id')->withTimestamps();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class, 'staff_conversation_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<int, array{name: string, url: string, mime: string|null, size: int, created_at: mixed, thumbnail: array<string, string>|null}>
     */
    public function attachmentGallery(): array
    {
        return $this->media
            ->whereIn('collection_name', ['ticket_images', 'ticket_attachments'])
            ->sortBy('id')
            ->map(fn (Media $media) => [
                'ulid'       => $media->ulid,
                'name'       => $media->name,
                'url'        => route('grp.tasks.attachments.show', ['staffTask' => $this->reference, 'media' => $media->ulid]),
                'mime'       => $media->mime_type,
                'size'       => $media->size,
                'created_at' => $media->created_at,
                'thumbnail'  => $media->collection_name === 'ticket_images' ? GetPictureSources::run($media->getImage()->resize(400, 0)) : null,
            ])
            ->values()
            ->all();
    }

    public function hasAttachment(Media $media): bool
    {
        return $media->model_type === $this->getMorphClass()
            && (int) $media->model_id === $this->id
            && in_array($media->collection_name, ['ticket_images', 'ticket_attachments'], true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [StaffTaskStatusEnum::TODO, StaffTaskStatusEnum::IN_PROGRESS]);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * An organisation's tasks are the ones its staff raised, own or help on, so a task between two countries shows in both.
     */
    public function scopeWithin(Builder $query, Group|Organisation $parent): Builder
    {
        if ($parent instanceof Group) {
            return $query->where('staff_tasks.group_id', $parent->id);
        }

        $staffIds = DB::table('user_has_models')->where('model_type', 'Employee')->where('organisation_id', $parent->id)->select('user_id');

        return $query->where('staff_tasks.group_id', $parent->group_id)
            ->where(fn (Builder $task) => $task
                ->whereIn('staff_tasks.requester_id', $staffIds)
                ->orWhereIn('staff_tasks.assignee_id', $staffIds)
                ->orWhereIn('staff_tasks.id', DB::table('staff_task_collaborators')->whereIn('user_id', $staffIds)->select('staff_task_id')));
    }

    /**
     * Supervisors, engineers and QA see every task, everyone else what they raised, own, help on or was sent to their department.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if (self::isSupervisor($viewer) || !self::canBeAssigned($viewer)) {
            return $query;
        }

        return $query->where(fn (Builder $task) => $task
            ->where('staff_tasks.requester_id', $viewer->id)
            ->orWhere('staff_tasks.assignee_id', $viewer->id)
            ->orWhereIn('staff_tasks.id', DB::table('staff_task_collaborators')->where('user_id', $viewer->id)->select('staff_task_id'))
            ->orWhereIn('staff_tasks.department', self::departmentsOf($viewer)));
    }

    public function isVisibleTo(User $viewer): bool
    {
        return $this->group_id === $viewer->group_id && self::query()->whereKey($this->id)->visibleTo($viewer)->exists();
    }

    /**
     * @return array{statuses: \Illuminate\Support\Collection, priorities: \Illuminate\Support\Collection}
     */
    public static function editOptions(): array
    {
        return [
            'statuses'   => collect(StaffTaskStatusEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value, 'icon' => StaffTaskStatusEnum::stateIcon()[$value]])->values(),
            'priorities' => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value, 'icon' => ChatPriorityEnum::stateIcon()[$value]])->values(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function involvedUserIds(): array
    {
        return collect([$this->requester_id, $this->assignee_id])
            ->merge($this->collaborators()->pluck('users.id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isWorkedOnBy(User $user): bool
    {
        return $this->assignee_id === $user->id || $this->collaborators->contains('id', $user->id);
    }

    public function canReassignBy(User $user): bool
    {
        return $this->requester_id === $user->id
            || $this->assignee_id === $user->id
            || ($this->group_id === $user->group_id && self::isSupervisor($user));
    }

    /**
     * The subject, description and the task's own files belong to whoever raised it; a supervisor
     * of the group can tidy them too.
     */
    public function canEditContentBy(User $user): bool
    {
        return $this->requester_id === $user->id || ($this->group_id === $user->group_id && self::isSupervisor($user));
    }

    /**
     * A department can be included on an open task that has none yet, by whoever raised it, works on it, or supervises.
     */
    public function canAddDepartmentBy(User $user): bool
    {
        return $this->department === null && $this->isOpen() && ($this->canReassignBy($user) || $this->isWorkedOnBy($user));
    }

    /**
     * Once included, only the department itself decides it is not theirs: a member of it, never the person who raised the task.
     */
    public function canRemoveDepartmentBy(User $user): bool
    {
        return $this->department !== null
            && $this->requester_id !== $user->id
            && in_array($this->department, self::departmentsOf($user), true);
    }

    public function canAskForHelpBy(User $user): bool
    {
        return $this->isOpen() && $this->isWorkedOnBy($user);
    }

    public function canRemoveCollaboratorsBy(User $user): bool
    {
        return $this->assignee_id === $user->id || $this->canSetDueDate($user);
    }

    public function canChangeCollaboratorsBy(User $user): bool
    {
        return $this->canRemoveCollaboratorsBy($user) || $this->isWorkedOnBy($user);
    }

    /**
     * The task maker sets the due date. A supervisor can too, unless they are working on the task
     * themselves: then, like any assignee or collaborator, they ask for a new ETA instead.
     */
    public function canSetDueDate(User $user): bool
    {
        if ($this->requester_id === $user->id) {
            return true;
        }

        return $this->group_id === $user->group_id && !$this->isWorkedOnBy($user) && self::isSupervisor($user);
    }

    public function canSuggestEta(User $user): bool
    {
        return $this->isOpen() && $this->isWorkedOnBy($user) && $this->requester_id !== $user->id;
    }

    /**
     * @return array{can_set: bool, can_suggest: bool}
     */
    public function dueAccessFor(User $user): array
    {
        return [
            'can_set'     => $this->canSetDueDate($user),
            'can_suggest' => $this->canSuggestEta($user),
        ];
    }

    public static function departmentLabel(string $department): string
    {
        return Str::headline(str_replace('-', ' ', $department));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public const string EXCLUDED_DEPARTMENT = 'help-desk';

    /**
     * Engineers and QA get tickets, not tasks: a user whose every job position is help desk sees everything but cannot be assigned.
     */
    public static function canBeAssigned(User $user): bool
    {
        $departments = DB::table('job_positions')
            ->where(fn ($query) => $query
                ->whereIn('id', DB::table('user_has_pseudo_job_positions')->where('user_id', $user->id)->select('job_position_id'))
                ->orWhereIn('id', DB::table('employee_has_job_positions')
                    ->whereIn('employee_id', DB::table('user_has_models')->where('user_id', $user->id)->where('model_type', 'Employee')->select('model_id'))
                    ->select('job_position_id')))
            ->pluck('department');

        return $departments->isEmpty() || $departments->contains(fn ($department) => $department !== self::EXCLUDED_DEPARTMENT);
    }

    public static function departments(int $groupId): array
    {
        return DB::table('job_positions')
            ->where('group_id', $groupId)
            ->whereNotNull('department')
            ->where('department', '!=', self::EXCLUDED_DEPARTMENT)
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->map(fn (string $department) => ['value' => $department, 'label' => self::departmentLabel($department)])
            ->all();
    }

    public static function departmentsOf(User $user): array
    {
        return DB::table('job_positions')
            ->whereNotNull('department')
            ->where('department', '!=', self::EXCLUDED_DEPARTMENT)
            ->where(fn ($query) => $query
                ->whereIn('id', DB::table('user_has_pseudo_job_positions')->where('user_id', $user->id)->select('job_position_id'))
                ->orWhereIn('id', DB::table('employee_has_job_positions')
                    ->whereIn('employee_id', DB::table('user_has_models')->where('user_id', $user->id)->where('model_type', 'Employee')->select('model_id'))
                    ->select('job_position_id')))
            ->distinct()
            ->pluck('department')
            ->all();
    }

    /**
     * Supervisors of a department in the requester's organisations, job position codes ending in -m.
     * Scoped to the requester's organisations so a warehouse task in one country does not wake every warehouse in the group.
     */
    public static function departmentSupervisors(User $requester, string $department): Collection
    {
        return self::departmentPeople($requester, $department, true);
    }

    /**
     * Everyone in a department in the requester's organisations, for a task sent to the department as a whole.
     */
    public static function departmentMembers(User $requester, string $department): Collection
    {
        return self::departmentPeople($requester, $department, false);
    }

    private static function departmentPeople(User $requester, string $department, bool $supervisorsOnly): Collection
    {
        $organisationIds = DB::table('user_has_models')
            ->join('employees', 'employees.id', '=', 'user_has_models.model_id')
            ->where('user_has_models.model_type', 'Employee')
            ->where('user_has_models.user_id', $requester->id)
            ->pluck('employees.organisation_id')
            ->merge(DB::table('user_has_authorised_models')->where('model_type', 'Organisation')->where('user_id', $requester->id)->pluck('model_id'))
            ->filter()->unique()->values()->all();

        $jobPositionIds = DB::table('job_positions')
            ->where('group_id', $requester->group_id)
            ->where('department', $department)
            ->when($supervisorsOnly, fn ($query) => $query->where('code', 'like', '%-m'))
            ->where(fn ($query) => $query->whereNull('organisation_id')->orWhereIn('organisation_id', $organisationIds))
            ->select('id');

        return User::query()
            ->where('group_id', $requester->group_id)
            ->where('status', true)
            ->where(fn ($query) => $query
                ->whereIn('id', DB::table('user_has_pseudo_job_positions')->whereIn('job_position_id', $jobPositionIds)->select('user_id'))
                ->orWhereIn('id', DB::table('user_has_models')->where('model_type', 'Employee')
                    ->whereIn('model_id', DB::table('employee_has_job_positions')->whereIn('job_position_id', $jobPositionIds)->select('employee_id'))
                    ->select('user_id')))
            ->get();
    }

    /**
     * Anyone holding a supervisor job position, code ending in -m, in any department.
     */
    public static function isSupervisor(User $user): bool
    {
        $supervisorPositions = DB::table('job_positions')->where('group_id', $user->group_id)->where('code', 'like', '%-m')->where('department', '!=', self::EXCLUDED_DEPARTMENT)->select('id');

        return DB::table('user_has_pseudo_job_positions')->where('user_id', $user->id)->whereIn('job_position_id', $supervisorPositions)->exists()
            || DB::table('employee_has_job_positions')
                ->whereIn('employee_id', DB::table('user_has_models')->where('user_id', $user->id)->where('model_type', 'Employee')->select('model_id'))
                ->whereIn('job_position_id', $supervisorPositions)->exists();
    }
}
