<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $employee_id
 * @property string $employee_name
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property int $duration_days
 * @property string|null $reason
 * @property LeaveStatusEnum $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $is_half_day
 * @property string $session
 * @property string $type
 * @property int|null $leave_type_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\LeaveApprovalRecord> $approvalRecords
 * @property-read User|null $approver
 * @property-read \App\Models\HumanResources\Employee|null $employee
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\HumanResources\LeaveType|null $leaveType
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Leave withoutTrashed()
 * @mixin \Eloquent
 */
class Leave extends Model implements HasMedia
{
    use InOrganisation;
    use SoftDeletes;
    use InteractsWithMedia;

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'approved_at'  => 'datetime',
        'status'       => LeaveStatusEnum::class,
        'data'         => 'array',
        'is_half_day'  => 'boolean',
    ];

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(LeaveApprovalRecord::class);
    }

    public function currentApprovalLevel(): int
    {
        $levels = $this->approvalLevels();

        if ($levels === []) {
            return 1;
        }

        $approvedLevels = $this->approvalRecords()
            ->approved()
            ->pluck('sequence_number')
            ->map(fn ($level) => (int) $level)
            ->unique()
            ->values()
            ->all();

        foreach ($levels as $level) {
            if (!in_array($level, $approvedLevels, true)) {
                return $level;
            }
        }

        return $levels[array_key_last($levels)];
    }

    public function canBeApprovedBy(User $user): bool
    {
        if ($this->status !== LeaveStatusEnum::PENDING) {
            return false;
        }

        $approvalLevel = $this->approvalLevelForUser($user);

        return $approvalLevel !== null;
    }

    public function approvalLevelForUser(User $user): ?int
    {
        $userLevels = $this->activeApproverLevelsForUser($user);

        if ($userLevels === []) {
            return null;
        }

        $currentLevel = $this->currentApprovalLevel();

        if (in_array($currentLevel, $userLevels, true)) {
            return $currentLevel;
        }

        $highestLevel = $this->highestApprovalLevel();
        if ($highestLevel === null) {
            return null;
        }

        if (in_array($highestLevel, $userLevels, true)) {
            return $highestLevel;
        }

        return null;
    }

    public function nextApprovalLevelAfter(int $level): ?int
    {
        foreach ($this->approvalLevels() as $approvalLevel) {
            if ($approvalLevel > $level) {
                return $approvalLevel;
            }
        }

        return null;
    }

    public function highestApprovalLevel(): ?int
    {
        $levels = $this->approvalLevels();

        if ($levels === []) {
            return null;
        }

        return $levels[array_key_last($levels)];
    }

    public function totalApprovalSteps(): int
    {
        return count($this->approvalLevels());
    }

    public function completedApprovalSteps(): int
    {
        $levels = $this->approvalLevels();

        if ($levels === []) {
            return 0;
        }

        $approvedLevels = $this->approvalRecords()
            ->approved()
            ->pluck('sequence_number')
            ->map(fn ($level) => (int) $level)
            ->unique()
            ->values()
            ->all();

        return count(array_intersect($levels, $approvedLevels));
    }

    public function currentApprovalStep(): int
    {
        $total = $this->totalApprovalSteps();
        if ($total === 0) {
            return 0;
        }

        return min($this->completedApprovalSteps() + 1, $total);
    }

    public function approvalLevels(): array
    {
        return LeaveApprover::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('is_active', true)
            ->orderBy('sequence_number')
            ->pluck('sequence_number')
            ->map(fn ($level) => (int) $level)
            ->unique()
            ->values()
            ->all();
    }

    public function activeApproverLevelsForUser(User $user): array
    {
        return LeaveApprover::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('is_active', true)
            ->where('user_id', $user->id)
            ->orderBy('sequence_number')
            ->pluck('sequence_number')
            ->map(fn ($level) => (int) $level)
            ->unique()
            ->values()
            ->all();
    }

    public function isPendingApproval(): bool
    {
        return $this->status === LeaveStatusEnum::PENDING
            && $this->approvalRecords()
                ->where('status', '!=', 'rejected')
                ->where('sequence_number', '<', $this->currentApprovalLevel())
                ->where('status', 'approved')
                ->count() >= 0;
    }
}
