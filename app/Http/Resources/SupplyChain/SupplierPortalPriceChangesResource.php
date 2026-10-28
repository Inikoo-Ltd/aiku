<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A supplier product with the last price change proposed for it, if any.
 *
 * @mixin SupplierProduct
 */
class SupplierPortalPriceChangesResource extends JsonResource
{
    public function toArray($request): array
    {
        $priceChange = null;
        if ($this->price_change_id) {
            $state    = SupplierSubmissionStateEnum::from($this->price_change_state);
            $data     = json_decode($this->price_change_data, true) ?? [];
            $editable = $state->isEditableBySupplier(SupplierSubmissionTypeEnum::PRICE_CHANGE);

            $priceChange = [
                'id'             => $this->price_change_id,
                'type'           => SupplierSubmissionTypeEnum::PRICE_CHANGE->value,
                'state'          => $state->value,
                'state_label'    => SupplierSubmissionStateEnum::labels()[$state->value],
                'proposed_price' => (float) ($data['proposed_price'] ?? 0),
                'effective_from' => $data['effective_from'] ?? null,
                'reason'         => $data['reason'] ?? null,
                'feedback'       => $this->price_change_feedback,
                'submitted_at'   => $this->price_change_submitted_at,
                'editable'       => $editable,
                'data'           => $editable ? [...$data, 'supplier_product_id' => $this->id] : null,
            ];
        }

        $status = match (true) {
            in_array($this->state, [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED], true) => 'discontinued',
            !$this->is_available => 'on_hold',
            default => 'active',
        };

        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'name'         => $this->name,
            'status'       => $status,
            'status_label' => ['active' => __('Active'), 'on_hold' => __('On hold'), 'discontinued' => __('Discontinued')][$status],
            'cost'         => (float) $this->cost,
            'price_change' => $priceChange,
        ];
    }
}
