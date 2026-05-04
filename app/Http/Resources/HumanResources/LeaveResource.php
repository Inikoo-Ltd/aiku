<?php

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
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
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $typeCode = is_string($this->type) ? $this->type : null;
        $leaveTypeSettingsValue = data_get($this->leaveType?->settings, 'value');

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
            'leave_type_value'  => is_numeric($leaveTypeSettingsValue)
                ? (float) $leaveTypeSettingsValue
                : 1.0,
            'session'           => $this->session,
            'reason'            => $this->reason,
            'status'            => $this->status?->value,
            'status_label'      => LeaveStatusEnum::labels()[$this->status?->value] ?? $this->status?->value,
            'status_color'      => LeaveStatusEnum::colors()[$this->status?->value] ?? 'gray',
            'status_icon'       => LeaveStatusEnum::stateIcon()[$this->status?->value] ?? null,
            'approval_total_steps' => $this->totalApprovalSteps(),
            'approval_completed_steps' => $this->completedApprovalSteps(),
            'approval_current_step' => $this->currentApprovalStep(),
            'can_approve_current_user' => Auth::check() ? $this->canBeApprovedBy(Auth::user()) : false,
            'approved_by'       => $this->approved_by,
            'approved_at'       => $this->approved_at?->toISOString(),
            'rejection_reason'  => $this->rejection_reason,
            'created_at'        => $this->created_at?->toISOString(),
            'attachments'       => $attachments,
        ];
    }
}
