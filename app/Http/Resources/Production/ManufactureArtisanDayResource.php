<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayShortReasonEnum;
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

        return [
            'id'                     => $this->id,
            'date'                   => $this->date->toDateString(),
            'user_id'                => $this->user_id,
            'worker'                 => $this->worker,
            'shift_start'            => $this->shift_start?->copy()->setTimezone($timezone)->format('H:i'),
            'shift_end'              => $this->shift_end?->copy()->setTimezone($timezone)->format('H:i'),
            'expected_minutes'       => $this->expected_minutes,
            'recorded_minutes'       => $this->recorded_minutes,
            'clocked_minutes'        => $this->clocked_minutes,
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
