<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Tasks;

use App\Models\Chat\StaffConversation;

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
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
class StaffTask extends Model implements Auditable
{
    use SoftDeletes;
    use HasHistory;

    public const array LINKABLE_MODELS = ['Product', 'Customer', 'Order', 'DeliveryNote'];

    protected $guarded = [];

    protected $attributes = [
        'data'     => '{}',
        'status'   => StaffTaskStatusEnum::TODO,
        'priority' => ChatPriorityEnum::NORMAL,
    ];

    protected array $auditInclude = ['status', 'assignee_id', 'department', 'priority', 'due_at', 'subject'];

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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class, 'staff_conversation_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [StaffTaskStatusEnum::TODO, StaffTaskStatusEnum::IN_PROGRESS]);
    }

    public static function departmentLabel(string $department): string
    {
        return Str::headline(str_replace('-', ' ', $department));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function departments(int $groupId): array
    {
        return DB::table('job_positions')
            ->where('group_id', $groupId)
            ->whereNotNull('department')
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
            ->where('code', 'like', '%-m')
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
        $supervisorPositions = DB::table('job_positions')->where('group_id', $user->group_id)->where('code', 'like', '%-m')->select('id');

        return DB::table('user_has_pseudo_job_positions')->where('user_id', $user->id)->whereIn('job_position_id', $supervisorPositions)->exists()
            || DB::table('employee_has_job_positions')
                ->whereIn('employee_id', DB::table('user_has_models')->where('user_id', $user->id)->where('model_type', 'Employee')->select('model_id'))
                ->whereIn('job_position_id', $supervisorPositions)->exists();
    }
}
