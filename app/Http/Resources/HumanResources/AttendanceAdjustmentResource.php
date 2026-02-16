<?php

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $employee_name
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $original_start_at
 * @property \Illuminate\Support\Carbon|null $original_end_at
 * @property \Illuminate\Support\Carbon|null $requested_start_at
 * @property \Illuminate\Support\Carbon|null $requested_end_at
 * @property string $reason
 * @property AttendanceAdjustmentStatusEnum $status
 */
class AttendanceAdjustmentResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                  => $this->id,
            'employee_id'         => $this->employee_id,
            'employee_name'       => $this->employee_name,
            'timesheet_id'        => $this->timesheet_id,
            'date'                => $this->date?->format('Y-m-d'),
            'original_start_at'   => $this->original_start_at?->toISOString(),
            'original_end_at'     => $this->original_end_at?->toISOString(),
            'requested_start_at'  => $this->requested_start_at?->toISOString(),
            'requested_end_at'    => $this->requested_end_at?->toISOString(),
            'reason'              => $this->reason,
            'status'              => $this->status?->value,
            'status_label'        => AttendanceAdjustmentStatusEnum::labels()[$this->status?->value] ?? $this->status?->value,
            'status_color'        => AttendanceAdjustmentStatusEnum::colors()[$this->status?->value] ?? 'gray',
            'status_icon'         => AttendanceAdjustmentStatusEnum::stateIcon()[$this->status?->value] ?? null,
            'approved_by'         => $this->approved_by,
            'approved_at'         => $this->approved_at?->toISOString(),
            'approval_comment'    => $this->approval_comment,
            'created_at'          => $this->created_at?->toISOString(),
            'attachments'         => $this->getMedia('attachments')?->map(fn ($media) => [
                'id'   => $media->id,
                'name' => $media->file_name,
                'url'  => $media->getUrl(),
            ]),
        ];
    }
}
