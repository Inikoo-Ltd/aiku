<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class SupplierPortalPurchaseOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        $open = in_array($this->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true);

        return [
            'reference'             => $this->reference,
            'organisation_name'     => $this->organisation_name,
            'state'                 => $this->state->value,
            'state_label'           => PurchaseOrderStateEnum::labels()[$this->state->value] ?? $this->state->value,
            'delivery_label'        => $this->delivery_state ? (PurchaseOrderDeliveryStateEnum::labels()[$this->delivery_state->value] ?? null) : null,
            'open'                  => $open,
            'submitted_at'          => $this->submitted_at,
            'confirmed_at'          => $this->confirmed_at,
            'estimated_received_at' => $this->estimated_received_at,
            'settled_at'            => $this->state === PurchaseOrderStateEnum::SETTLED ? $this->settled_at : null,
            'number_lines'          => (int) $this->number_lines,
            'cost_items'            => (float) $this->cost_items,
            'currency_code'         => $this->currency_code,
            'labels'                => $open
                ? collect(PdfPurchaseOrderLabels::LEVELS)->mapWithKeys(fn (string $level) => [$level => PdfPurchaseOrderLabels::make()->url($this->resource, [$level])])->all()
                : null,
        ];
    }
}
