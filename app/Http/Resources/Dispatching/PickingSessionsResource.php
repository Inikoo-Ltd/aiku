<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $state
 * @property string|null $start_at
 * @property string|null $end_at
 * @property int $number_delivery_notes
 * @property int $number_items
 * @property int $user_id
 * @property string $user_username
 * @property string $user_name
 */
class PickingSessionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'reference'             => $this->reference,
            'state'                 => $this->state,
            'start_at'              => $this->start_at,
            'end_at'                => $this->end_at,
            'number_delivery_notes' => $this->number_delivery_notes,
            'number_items'          => $this->number_items,
            'user_id'               => $this->user_id,
            'user_username'         => $this->user_username,
            'user_name'             => $this->user_name,

        ];
    }
}
