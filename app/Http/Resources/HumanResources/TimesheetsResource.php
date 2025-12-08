<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property mixed $date
 * @property mixed $start_at
 * @property mixed $end_at
 * @property mixed $working_duration
 * @property mixed $breaks_duration
 * @property int $number_time_trackers
 * @property int $number_open_time_trackers
 * @property string $subject_name
 */
class TimesheetsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {

        return [
            'id' => $this->id,
            'date' => $this->date,
            'subject_name' => $this->subject_name,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'working_duration' => $this->working_duration,
            'breaks_duration' => $this->breaks_duration,
            'number_time_trackers' => $this->number_time_trackers,
            'number_open_time_trackers' => $this->number_open_time_trackers,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,

        ];
    }
}
