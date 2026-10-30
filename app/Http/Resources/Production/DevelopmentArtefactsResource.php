<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property mixed $state
 * @property array|string|null $data
 * @property int $recipe_steps
 * @property int $trial_runs
 * @property int $trial_runs_received
 * @property string|null $org_stock_code
 * @property bool $is_on_sale
 * @property string|null $branched_from_code
 * @property string|null $branched_from_slug
 */
class DevelopmentArtefactsResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->state instanceof ArtefactStateEnum ? $this->state : ArtefactStateEnum::from($this->state);
        $data  = is_array($this->data) ? $this->data : (json_decode((string) $this->data, true) ?: []);

        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'state'               => ArtefactStateEnum::stateIcon()[$state->value],
            'recipe_steps'        => (int) $this->recipe_steps,
            'trial_runs'          => (int) $this->trial_runs,
            'trial_runs_received' => (int) $this->trial_runs_received,
            'org_stock_code'      => $this->org_stock_code,
            'is_on_sale'          => (bool) $this->is_on_sale,
            'is_ready'            => $this->recipe_steps > 0 && $this->org_stock_code !== null && $this->is_on_sale,
            'launched_at'         => Arr::get($data, 'launch.at'),
            'launched_by'         => Arr::get($data, 'launch.name'),
            'cancelled_at'        => Arr::get($data, 'cancellation.at'),
            'cancelled_by'        => Arr::get($data, 'cancellation.name'),
            'cancelled_reason'    => Arr::get($data, 'cancellation.reason'),
            'branched_from_code'  => $this->branched_from_code,
            'branched_from_slug'  => $this->branched_from_slug,
            'merged_at'           => Arr::get($data, 'merge.at'),
            'merged_by'           => Arr::get($data, 'merge.name'),
        ];
    }
}
