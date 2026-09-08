<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property JobOrderStateEnum $state
 * @property string|null $date
 * @property int $number_tasks
 * @property int $number_tasks_done
 */
class JobOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'state'             => $this->state,
            'state_label'       => JobOrderStateEnum::labels()[$this->state->value] ?? $this->state->value,
            'date'              => $this->date,
            'number_tasks'      => (int)$this->number_tasks,
            'number_tasks_done' => (int)$this->number_tasks_done,
        ];
    }
}
