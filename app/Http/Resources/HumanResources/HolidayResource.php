<?php

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\Holiday;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use App\Enums\HumanResources\Holiday\HolidayTypeEnum;

class HolidayResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Holiday $holiday */
        $holiday = $this;

        return [
            'id'                => $holiday->id,
            'type'              => $holiday->type?->value,
            'type_label'        => HolidayTypeEnum::labels()[$holiday->type?->value] ?? $holiday->type?->value,
            'year'              => $holiday->year,
            'label'             => $holiday->label,
            'from'              => $holiday->from?->format('Y-m-d'),
            'to'                => $holiday->to?->format('Y-m-d'),
            'duration_days'     => $holiday->from && $holiday->to ? $holiday->from->diffInDays($holiday->to) + 1 : null,
            'is_recurring'      => (bool) ($holiday->data['is_recurring'] ?? false),
            'organisation_name' => $holiday->organisation->name ?? null,
            'organisation_slug' => $holiday->organisation->slug ?? null,
        ];
    }
}
