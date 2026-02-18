<?php

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $employee_name
 * @property LeaveTypeEnum $type
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property int $duration_days
 * @property string|null $reason
 * @property LeaveStatusEnum $status
 */
class LeaveResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                => $this->id,
            'employee_id'       => $this->employee_id,
            'employee_name'     => $this->employee_name,
            'type'              => $this->type?->value,
            'type_label'        => LeaveTypeEnum::labels()[$this->type?->value] ?? $this->type?->value,
            'type_color'        => LeaveTypeEnum::colors()[$this->type?->value] ?? 'gray',
            'start_date'        => $this->start_date?->format('Y-m-d'),
            'end_date'          => $this->end_date?->format('Y-m-d'),
            'duration_days'     => $this->duration_days,
            'reason'            => $this->reason,
            'status'            => $this->status?->value,
            'status_label'      => LeaveStatusEnum::labels()[$this->status?->value] ?? $this->status?->value,
            'status_color'      => LeaveStatusEnum::colors()[$this->status?->value] ?? 'gray',
            'status_icon'       => LeaveStatusEnum::stateIcon()[$this->status?->value] ?? null,
            'approved_by'       => $this->approved_by,
            'approved_at'       => $this->approved_at?->toISOString(),
            'rejection_reason'  => $this->rejection_reason,
            'created_at'        => $this->created_at?->toISOString(),
            'attachments'       => $this->getMedia('attachments')?->map(fn ($media) => [
                'id'   => $media->id,
                'name' => $media->file_name,
                'url'  => route('grp.media.download', ['media' => $media->ulid]),
            ]),
        ];
    }
}
