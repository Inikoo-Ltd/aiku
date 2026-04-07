<?php

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\LeaveApprover;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $employee_name
 * @property string $type
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property int $duration_days
 * @property bool $is_half_day
 * @property string $session
 * @property string|null $reason
 * @property LeaveStatusEnum $status
 */
class LeaveResource extends JsonResource
{
    protected static array $approverConfigCache = [];

    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $typeCode = is_string($this->type) ? $this->type : null;

        $this->loadMissing(['approvalRecords.approver']);

        $approverConfig = static::$approverConfigCache[$this->organisation_id]
            ??= $this->buildApproverConfig();
        $totalSteps = $approverConfig['total_steps'];
        $approverNames = $approverConfig['approver_names'];

        $approvalProgress = [];
        for ($i = 1; $i <= $totalSteps; $i++) {
            $records = $this->approvalRecords->where('sequence_number', $i);
            $approvedRecord = $records->firstWhere('status', 'approved');
            $rejectedRecord = $records->firstWhere('status', 'rejected');

            if ($approvedRecord) {
                $stepStatus = 'approved';
                $approverName = $approverNames->get($approvedRecord->approver_id . ':' . $approvedRecord->sequence_number)
                    ?? $approvedRecord->approver?->contact_name
                    ?? $approvedRecord->approver?->username
                    ?? $approvedRecord->approver?->name;
            } elseif ($rejectedRecord) {
                $stepStatus = 'rejected';
                $approverName = $approverNames->get($rejectedRecord->approver_id . ':' . $rejectedRecord->sequence_number)
                    ?? $rejectedRecord->approver?->contact_name
                    ?? $rejectedRecord->approver?->username
                    ?? $rejectedRecord->approver?->name;
            } elseif ($records->isNotEmpty()) {
                $stepStatus = 'pending';
                $approverName = null;
            } else {
                $stepStatus = 'waiting';
                $approverName = null;
            }

            $approvalProgress[] = [
                'level' => $i,
                'status' => $stepStatus,
                'approver_name' => $approverName,
            ];
        }

        $allAcceptedRecord = $this->approvalRecords->firstWhere(
            'sequence_number',
            LeaveApprover::SEQUENCE_ALL_ACCEPTED
        );
        $allAcceptedApproverName = null;
        if ($allAcceptedRecord) {
            $allAcceptedApproverName = $approverNames->get(
                $allAcceptedRecord->approver_id . ':' . $allAcceptedRecord->sequence_number
            )
                ?? $allAcceptedRecord->approver?->contact_name
                ?? $allAcceptedRecord->approver?->username
                ?? $allAcceptedRecord->approver?->name;
        }

        $attachments = [];
        $mediaItems = $this->getMedia('attachments');
        if ($mediaItems && count($mediaItems) > 0) {
            foreach ($mediaItems as $media) {
                $attachments[] = [
                    'id'   => $media->id,
                    'name' => $media->file_name,
                    'url'  => route('grp.media.download', ['media' => $media->ulid]),
                ];
            }
        }

        return [
            'id'                => $this->id,
            'employee_id'       => $this->employee_id,
            'employee_name'     => $this->employee_name,
            'type'              => $typeCode,
            'type_label'        => $this->leaveType?->name ?? $typeCode,
            'type_color'        => $this->leaveType?->color ?? 'gray',
            'start_date'        => $this->start_date?->format('Y-m-d'),
            'end_date'          => $this->end_date?->format('Y-m-d'),
            'duration_days'     => $this->duration_days,
            'is_half_day'       => $this->is_half_day,
            'session'           => $this->session,
            'reason'            => $this->reason,
            'status'            => $this->status?->value,
            'status_label'      => LeaveStatusEnum::labels()[$this->status?->value] ?? $this->status?->value,
            'status_color'      => LeaveStatusEnum::colors()[$this->status?->value] ?? 'gray',
            'status_icon'       => LeaveStatusEnum::stateIcon()[$this->status?->value] ?? null,
            'approved_by'       => $this->approved_by,
            'approved_at'       => $this->approved_at?->toISOString(),
            'rejection_reason'  => $this->rejection_reason,
            'created_at'        => $this->created_at?->toISOString(),
            'attachments'       => $attachments,
            'approval_progress' => $approvalProgress,
            'all_accepted_approval' => $allAcceptedRecord ? [
                'status' => $allAcceptedRecord->status,
                'approver_name' => $allAcceptedApproverName,
            ] : null,
        ];
    }

    protected function buildApproverConfig(): array
    {
        $approvers = LeaveApprover::query()
            ->where('organisation_id', $this->organisation_id)
            ->get(['user_id', 'sequence_number', 'name']);

        $numberedApprovers = $approvers->filter(
            fn (LeaveApprover $approver) => $approver->sequence_number > LeaveApprover::SEQUENCE_ALL_ACCEPTED
        );

        return [
            'total_steps' => $numberedApprovers->max('sequence_number') ?? 1,
            'approver_names' => $approvers->mapWithKeys(function (LeaveApprover $approver) {
                return [$approver->user_id . ':' . $approver->sequence_number => $approver->name];
            }),
        ];
    }
}
