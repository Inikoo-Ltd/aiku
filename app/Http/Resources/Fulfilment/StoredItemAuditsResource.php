<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $fulfilment_customer_name
 * @property string $fulfilment_customer_id
 * @property string $fulfilment_customer_slug
 * @property string $public_notes
 * @property string $internal_notes
 * @property StoredItemAuditStateEnum $state
 */
class StoredItemAuditsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'fulfilment_customer_name' => $this->fulfilment_customer_name,
            'fulfilment_customer_slug' => $this->fulfilment_customer_slug,
            'fulfilment_customer_id' => $this->fulfilment_customer_id,
            'public_notes' => $this->public_notes,
            'internal_notes' => $this->internal_notes,
            'state' => $this->state,
            'state_label' => $this->state->labels()[$this->state->value],
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'number_audited_pallets' => $this->number_audited_pallets,
            'number_audited_stored_items' => $this->number_audited_stored_items,
            'number_audited_stored_items_with_additions' => $this->number_audited_stored_items_with_additions,
            'number_audited_stored_items_with_with_subtractions' => $this->number_audited_stored_items_with_with_subtractions,
            'number_audited_stored_items_with_with_stock_checked' => $this->number_audited_stored_items_with_with_stock_checked,
            'number_associated_stored_items' => $this->number_associated_stored_items,
            'number_created_stored_items' => $this->number_created_stored_items,
            'created_at' => $this->created_at,
        ];
    }
}
