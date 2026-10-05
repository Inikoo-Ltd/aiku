<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 20:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $state
 * @property mixed $times_ordered
 * @property mixed $average_quantity
 * @property mixed $average_days_between
 * @property mixed $last_ordered_on
 * @property mixed $next_order_on
 * @property mixed $is_due
 */
class CustomerReorderProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'state'                => $this->state,
            'times_ordered'        => (int) $this->times_ordered,
            'average_quantity'     => round((float) $this->average_quantity, 1),
            'average_days_between' => (int) $this->average_days_between,
            'last_ordered_on'      => $this->last_ordered_on,
            'next_order_on'        => $this->next_order_on,
            'is_due'               => (bool) $this->is_due,
        ];
    }
}
