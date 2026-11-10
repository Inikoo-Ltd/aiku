<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayShortReasonEnum;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeCompensationEnum;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeDecisionEnum;
use App\Models\Production\ManufactureArtisanDay;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ManufactureArtisanDay
 * @property string|null $worker
 * @property string|null $resolved_by
 * @property int|null $difference_minutes
 */
class ManufactureArtisanDayResource extends JsonResource
{
    public function toArray($request): array
    {
        $timezone = $this->employee?->organisation?->timezone?->name ?? config('app.timezone');
        $overtime = $this->overtime;

        return [
            'id'                     => $this->id,
            'employee_id'            => $this->employee_id,
            'date'                   => $this->date->toDateString(),
            'is_weekend'             => $this->date->isWeekend(),
            'clocked_in_at'          => $this->clocked_in_at?->copy()->setTimezone($timezone)->format('H:i'),
            'clocked_out_at'         => $this->clocked_out_at?->copy()->setTimezone($timezone)->format('H:i'),
            'overtime'               => $overtime ? [
                'planned_start'      => $overtime->planned_start?->copy()->setTimezone($timezone)->format('H:i'),
                'planned_end'        => $overtime->planned_end?->copy()->setTimezone($timezone)->format('H:i'),
                'decision'           => $overtime->decision->value,
                'decision_label'     => ManufactureOvertimeDecisionEnum::labels()[$overtime->decision->value],
                'compensation'       => $overtime->compensation?->value,
                'compensation_label' => $overtime->compensation ? ManufactureOvertimeCompensationEnum::labels()[$overtime->compensation->value] : null,
                'reason'             => $overtime->reason,
                'decided_by'         => $overtime->decidedBy?->contact_name ?: $overtime->decidedBy?->username,
                'decided_at'         => $overtime->decided_at,
            ] : null,
            'is_outside_planned'     => $this->isOutsidePlanned(),
            'is_overtime_decided'    => $this->isOvertimeDecided(),
            'is_overtime_awaiting'   => $this->isOvertimeAwaitingDecision(),
            'user_id'                => $this->user_id,
            'worker'                 => $this->worker,
            'shift_start'            => $this->shift_start?->copy()->setTimezone($timezone)->format('H:i'),
            'shift_end'              => $this->shift_end?->copy()->setTimezone($timezone)->format('H:i'),
            'expected_minutes'       => $this->expected_minutes,
            'recorded_minutes'       => $this->recorded_minutes,
            'clocked_minutes'        => $this->clocked_minutes,
            'not_paid_minutes'       => $this->paid_seconds === null ? 0 : max(0, ($this->clocked_minutes ?? 0) - (int) round($this->paid_seconds / 60)),
            'tablet_over_clocked_minutes' => $this->tablet_over_clocked_minutes,
            'open_clockings'         => $this->open_clockings,
            'difference_minutes'     => $this->difference_minutes,
            'tolerance_minutes'      => $this->tolerance_minutes,
            'unchecked_running_jobs' => $this->unchecked_running_jobs,
            'status'                 => $this->status->value,
            'is_warning_open'        => $this->is_warning_open,
            'resolution'             => $this->resolution?->value,
            'resolution_label'       => $this->resolution ? ManufactureArtisanDayResolutionEnum::labels()[$this->resolution->value] : null,
            'resolution_reason'      => $this->resolution_reason ? ManufactureArtisanDayShortReasonEnum::labels()[$this->resolution_reason->value] : null,
            'resolution_note'        => $this->resolution_note,
            'resolved_minutes'       => $this->resolved_minutes,
            'is_resolution_current'  => $this->isResolved(),
            'resolved_by'            => $this->resolved_by,
            'resolved_at'            => $this->resolved_at,
            'resolve_route'          => [
                'name'       => 'grp.models.manufacture-artisan-day.resolve',
                'parameters' => ['manufactureArtisanDay' => $this->id],
            ],
        ];
    }
}
