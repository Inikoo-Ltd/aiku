<?php

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Holiday;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class HolidayResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Holiday $holiday */
        $holiday = $this;

        return [
            'id'                => $holiday->id,
            'type'              => $holiday->type->value,
            'type_label'        => $holiday->type->labels(),
            'year'              => $holiday->year,
            'label'             => $holiday->label,
            'from'              => $holiday->from,
            'to'                => $holiday->to,
            'duration_days'     => $holiday->from && $holiday->to ? $holiday->from->diffInDays($holiday->to) + 1 : null,
            'organisation_name' => $holiday->organisation->name ?? null,
            'organisation_slug' => $holiday->organisation->slug ?? null,
        ];
    }
}
