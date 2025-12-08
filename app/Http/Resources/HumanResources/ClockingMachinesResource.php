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
 * @property mixed $workplace_name
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $type
 * @property mixed $workplace_slug
 */
class ClockingMachinesResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'workplace_name' => $this->workplace_name,
            'workplace_slug' => $this->workplace_slug,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
