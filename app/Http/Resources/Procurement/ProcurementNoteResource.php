<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteStateEnum;
use App\Models\Procurement\ProcurementNote;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProcurementNote
 *
 * @property string|null $user_name
 * @property string|null $purchase_order_reference
 * @property string|null $supplier_product_code
 * @property string|null $incident_currency_code
 * @property string|null $listed_on
 * @property int|null $number_documents
 */
class ProcurementNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        $onPurchaseOrder = $this->subject_type === 'PurchaseOrder';
        $listedElsewhere = $this->listed_on !== 'PurchaseOrder';

        return [
            'id'                       => $this->id,
            'created_at'               => $this->created_at,
            'author'                   => $this->user_name ?? $this->author?->contact_name ?? $this->author_name,
            'author_user_id'           => $this->author_user_id,
            'organisation_code'        => $this->organisation_code ?? ($this->organisation_id ? null : __('All')),
            'title'                    => $this->title,
            'note'                     => $this->body,
            'category'                 => [
                'value'  => $this->category->value,
                'label'  => $this->category->label(),
                'colour' => $this->category->colour(),
            ],
            'severity'                 => $this->severity->value,
            'severity_label'           => $this->severity->label(),
            'visibility'               => $this->visibility->value,
            'visibility_label'         => $this->visibility->label(),
            'is_pinned'                => $this->is_pinned,
            'state'                    => $this->state->value,
            'state_label'              => $this->state->label(),
            'strikethrough'            => $this->state !== ProcurementNoteStateEnum::ACTIVE,
            'incident_amount'          => $this->incident_amount,
            'incident_currency_code'   => $this->incident_currency_code ?? null,
            'incident_outcome'         => $this->incident_outcome?->value,
            'incident_outcome_label'   => $this->incident_outcome?->label(),
            'incident_type'            => $this->incident_type?->value,
            'incident_type_label'      => $this->incident_type ? ProcurementNoteIncidentTypeEnum::labels()[$this->incident_type->value] : null,
            'incident_quantity'        => $this->incident_quantity,
            'supplier_product_code'    => $this->supplier_product_code ?? null,
            'purchase_order_reference' => $listedElsewhere ? ($this->purchase_order_reference ?? null) : null,
            'number_purchase_orders'   => $onPurchaseOrder && $listedElsewhere ? (int) $this->number_documents : null,
        ];
    }
}
