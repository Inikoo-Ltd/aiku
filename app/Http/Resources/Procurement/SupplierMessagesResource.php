<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Models\Procurement\SupplierMessage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SupplierMessage $resource
 */
class SupplierMessagesResource extends JsonResource
{
    public function toArray($request): array
    {
        $email      = $this->resource;
        $isOutbound = $email->direction === SupplierMessageDirectionEnum::OUTBOUND;

        return [
            'id'            => $email->id,
            'is_outbound'   => $isOutbound,
            'channel'       => $email->channel->value,
            'sent_at'       => $email->sent_at,
            'counterpart_name' => $email->counterpart_name,
            'counterpart_type' => $email->counterpart_type,
            'organisation_code' => $email->organisation_code,
            'correspondent' => $isOutbound
                ? collect($email->to)->pluck('address')->implode(', ')
                : ($email->from_name ?: $email->from_address),
            'subject'       => $email->subject ?: ($email->channel === SupplierMessageChannelEnum::EMAIL ? __('(no subject)') : __('WhatsApp')),
            'snippet'       => $email->snippet,
            'number_attachments' => count($email->attachments ?? []),
            'route'         => [
                'name'       => 'grp.org.procurement.supplier_messages.show',
                'parameters' => [request()->route('organisation')?->slug ?? $email->organisation_slug, $email->id],
            ],
        ];
    }
}
