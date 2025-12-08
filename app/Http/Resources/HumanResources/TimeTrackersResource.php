<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 21:27:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property mixed $starts_at
 * @property mixed $ends_at
 * @property mixed $duration
 * @property TimeTrackerStatusEnum $status
 */
class TimeTrackersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'duration' => $this->duration,
            'status' => $this->status->statusIcon()[$this->status->value],
            'action' => match (true) {
                (bool) $this->starts_at => [
                    'tooltip' => __('Clock In'),
                    'icon' => 'fal fa-clock',
                    'class' => 'text-green-500',
                ],
                (bool) $this->ends_at => [
                    'tooltip' => __('Clock Out'),
                    'icon' => 'fal fa-clock',
                    'class' => 'text-red-500',
                ],
                default => []
            },
        ];
    }
}
