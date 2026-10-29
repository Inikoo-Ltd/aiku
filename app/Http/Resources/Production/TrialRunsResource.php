<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property mixed $state
 * @property string|null $date
 * @property string|null $received_at
 * @property string $artefact_code
 * @property string|null $artefact_name
 * @property string $artefact_slug
 * @property float $quantity
 * @property float $quantity_received
 * @property string|null $artisan_name
 */
class TrialRunsResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->state instanceof JobOrderStateEnum ? $this->state : JobOrderStateEnum::from($this->state);

        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'state'             => $state->value,
            'state_label'       => JobOrderStateEnum::labels()[$state->value] ?? $state->value,
            'date'              => $this->date,
            'received_at'       => $this->received_at,
            'artefact_code'     => $this->artefact_code,
            'artefact_name'     => $this->artefact_name,
            'artefact_slug'     => $this->artefact_slug,
            'quantity'          => (float) $this->quantity,
            'quantity_received' => (float) $this->quantity_received,
            'artisan_name'      => $this->artisan_name,
        ];
    }
}
