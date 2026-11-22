<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\ReceivedEInvoice;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReceivedEInvoice
 * @property string|null $matched_supplier_code
 * @property string|null $matched_supplier_name
 * @property string|null $purchase_order_reference
 * @property string|null $stock_delivery_reference
 */
class ReceivedEInvoicesResource extends JsonResource
{
    public function toArray($request): array
    {
        $parameters = [request()->route('organisation'), $this->id];

        return [
            'id'                       => $this->id,
            'received_at'              => $this->received_at,
            'sender_name'              => $this->supplier_name ?: $this->sender_name,
            'sender_peppol_id'         => $this->sender_peppol_id,
            'document_type'            => $this->document_type,
            'document_number'          => $this->document_number,
            'issue_date'               => $this->issue_date?->toDateString(),
            'due_date'                 => $this->due_date?->toDateString(),
            'currency_code'            => $this->currency_code,
            'total_amount'             => $this->total_amount === null ? null : (float)$this->total_amount,
            'order_reference'          => $this->order_reference,
            'parse_error'              => $this->parse_error,
            'acknowledged'             => $this->acknowledged_at !== null,
            'match_state'              => $this->match_state->value,
            'suggestion'               => $this->match_data['suggestion'] ?? null,
            'decided_by'               => $this->match_data['decided_by'] ?? null,
            'decided_note'             => $this->match_data['decided_note'] ?? null,
            'supplier_id'              => $this->supplier_id,
            'supplier_code'            => $this->matched_supplier_code,
            'supplier_name'            => $this->matched_supplier_name,
            'purchase_order_reference' => $this->purchase_order_reference,
            'stock_delivery_reference' => $this->stock_delivery_reference,
            'xml_url'                  => $this->xml_sha256 ? route('grp.org.accounting.received_e_invoices.xml', $parameters) : null,
            'pdf_url'                  => $this->pdf_sha256 ? route('grp.org.accounting.received_e_invoices.pdf', $parameters) : null,
        ];
    }
}
