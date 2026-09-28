<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Models\Procurement\SupplierEmail;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SupplierEmail $resource
 */
class SupplierEmailsResource extends JsonResource
{
    public function toArray($request): array
    {
        $email      = $this->resource;
        $isOutbound = $email->direction === SupplierEmailDirectionEnum::OUTBOUND;

        return [
            'id'            => $email->id,
            'is_outbound'   => $isOutbound,
            'sent_at'       => $email->sent_at,
            'supplier_name' => $email->supplier_name,
            'organisation_code' => $email->organisation_code,
            'correspondent' => $isOutbound
                ? collect($email->to)->pluck('address')->implode(', ')
                : ($email->from_name ?: $email->from_address),
            'subject'       => $email->subject ?: __('(no subject)'),
            'snippet'       => $email->snippet,
            'number_attachments' => count($email->attachments ?? []),
            'route'         => [
                'name'       => 'grp.org.procurement.supplier_emails.show',
                'parameters' => [$email->organisation_slug, $email->id],
            ],
        ];
    }
}
