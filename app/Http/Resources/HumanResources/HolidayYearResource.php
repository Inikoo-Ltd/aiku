<?php

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayYearResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'label'           => $this->label,
            'start_date'      => $this->start_date->format('Y-m-d'),
            'end_date'        => $this->end_date->format('Y-m-d'),
            'is_active'       => $this->is_active,
            'organisation_id' => $this->organisation_id,
            'organisation'    => $this->whenLoaded('organisation', fn () => [
                'id'   => $this->organisation->id,
                'name' => $this->organisation->name,
                'slug' => $this->organisation->slug,
            ]),
        ];
    }
}
