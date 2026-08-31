<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WhatsappCampaignRecipientsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->recipient_key,
            'recipient_key'           => $this->recipient_key,
            'name'                    => $this->name,
            'phone_number'            => $this->phone_number,
            'customer_id'             => $this->customer_id,
            'meta_chat_session_id'    => $this->meta_chat_session_id,
            'sources'                 => array_values(array_filter([
                $this->is_contacted ? __('Contacted') : null,
                $this->is_subscriber ? __('Subscriber') : null,
                $this->is_customer ? __('Customer') : null,
            ])),
            'last_visitor_message_at' => $this->formatDate($this->last_visitor_message_at),
            'created_at'              => $this->formatDate($this->created_at),
        ];
    }

    /**
     * The union subquery returns raw timestamp strings rather than Carbon instances,
     * so they are parsed here instead of being formatted straight off the model.
     */
    private function formatDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->format('d F Y, H:i');
    }
}
