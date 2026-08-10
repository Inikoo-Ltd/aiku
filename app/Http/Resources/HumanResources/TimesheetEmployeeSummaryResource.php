<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property mixed $subject_type
 * @property mixed $subject_id
 * @property mixed $subject_name
 * @property mixed $job_position
 * @property mixed $timesheet_count
 * @property mixed $clockings
 * @property mixed $working_duration
 * @property mixed $breaks_duration
 * @property mixed $paid_duration
 * @property mixed $unpaid_overtime_duration
 * @property mixed $paid_overtime_duration
 * @property mixed $worked
 */
class TimesheetEmployeeSummaryResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $data = [
            'subject_type'             => $this->subject_type,
            'subject_id'               => $this->subject_id,
            'subject_name'             => $this->subject_name,
            'subject_slug'             => $this->subject_slug ?? null,
            'job_position'             => $this->job_position ?: '-',
            'timesheet_count'          => (int) $this->timesheet_count,
            'clockings'                => (int) $this->clockings,
            'working_duration'         => (int) $this->working_duration,
            'breaks_duration'          => (int) $this->breaks_duration,
            'paid_duration'            => (int) $this->paid_duration,
            'unpaid_overtime_duration' => (int) $this->unpaid_overtime_duration,
            'paid_overtime_duration'   => (int) $this->paid_overtime_duration,
            'worked'                   => (int) $this->worked,
        ];

        if ($this->hasWeekdayBreakdown()) {
            $data += [
                'monday'    => (int) $this->monday,
                'tuesday'   => (int) $this->tuesday,
                'wednesday' => (int) $this->wednesday,
                'thursday'  => (int) $this->thursday,
                'friday'    => (int) $this->friday,
                'saturday'  => (int) $this->saturday,
                'sunday'    => (int) $this->sunday,
                'work_week' => (int) $this->work_week,
                'weekend'   => (int) $this->weekend,
            ];
        }

        return $data;
    }

    private function hasWeekdayBreakdown(): bool
    {
        return array_key_exists('monday', $this->resource->getAttributes());
    }
}
