<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property \App\Enums\CRM\Livechat\ChatMessageStateEnum|string $state
 * @property string|null $message_text
 * @property string|null $sent_at
 * @property string|null $delivered_at
 * @property string|null $read_at
 * @property string|null $error
 * @property string|null $phone_number
 * @property string|null $customer_name
 * @property string|null $customer_slug
 * @property string|null $order_reference
 * @property string|null $order_slug
 */
class WhatsappOutboxMessagesResource extends JsonResource
{
    public function toArray($request): array
    {
        $state = $this->state instanceof \BackedEnum ? $this->state->value : $this->state;

        return [
            'id'              => $this->id,
            'state'           => $this->read_at ? 'read' : ($this->delivered_at ? 'delivered' : $state),
            'message_text'    => $this->message_text,
            'sent_at'         => $this->sent_at,
            'error'           => $this->error,
            'phone_number'    => $this->phone_number,
            'customer_name'   => $this->customer_name,
            'customer_slug'   => $this->customer_slug,
            'order_reference' => $this->order_reference,
            'order_slug'      => $this->order_slug,
        ];
    }
}
