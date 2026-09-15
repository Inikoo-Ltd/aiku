<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\Chat\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Builder;
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
}
