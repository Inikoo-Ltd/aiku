<?php

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Leave;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveCoversResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Leave $leave */
        $leave = $this->resource;

        return [
            'id'                    => $leave->id,
            'employee_id'           => $leave->employee_id,
            'employee_name'         => $leave->employee_name,
            'type_label'            => $leave->leaveType?->name ?? $leave->type,
            'start_date'            => $leave->start_date->format('Y-m-d'),
            'end_date'              => $leave->end_date->format('Y-m-d'),
            'is_ongoing'            => $leave->start_date->lte(today()),
            'cover_employee_id'     => $leave->cover_employee_id,
            'covered_by'            => $leave->coverEmployee?->contact_name,
        ];
    }
}
