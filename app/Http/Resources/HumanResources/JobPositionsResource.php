<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 15:20:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property string $slug
 * @property string $name
 * @property int $number_employees_currently_working
 * @property string $code
 * @property mixed $share
 */
class JobPositionsResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'number_employees_currently_working' => $this->number_employees_currently_working,
            'share' => percentage($this->share, 1),
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,

        ];
    }
}
