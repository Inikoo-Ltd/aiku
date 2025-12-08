<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 15 Sept 2022 14:55:27 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Production\ProductionStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int $number_raw_materials
 * @property int $number_artefacts
 * @property int $number_manufacture_tasks
 */
class ProductionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'state_icon' => ProductionStateEnum::stateIcon($this->state->value),
            'number_raw_materials' => $this->number_raw_materials,
            'number_artefacts' => $this->number_artefacts,
            'number_manufacture_tasks' => $this->number_manufacture_tasks,
        ];
    }
}
