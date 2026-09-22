<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatPhoneCallContactTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatPhoneCallResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status instanceof ChatPhoneCallStatusEnum
            ? $this->status
            : ChatPhoneCallStatusEnum::from($this->status);

        $contactType = $this->contact_type instanceof ChatPhoneCallContactTypeEnum
            ? $this->contact_type
            : ($this->contact_type ? ChatPhoneCallContactTypeEnum::from($this->contact_type) : null);

        return [
            'id'               => $this->id,
            'status'           => $status->value,
            'status_label'     => ChatPhoneCallStatusEnum::labels()[$status->value] ?? $status->value,
            'agent_name'       => $this->agent_name,
            'contact_type'     => $contactType?->value,
            'contact_label'    => $contactType ? (ChatPhoneCallContactTypeEnum::labels()[$contactType->value] ?? $contactType->value) : null,
            'contact_name'     => $this->contact_name,
            'shop_name'        => $this->shop_name,
            'notes'            => $this->notes,
            'started_at'       => $this->started_at,
            'ended_at'         => $this->ended_at,
            'duration_seconds' => $this->duration_seconds,
            'customer_id'      => $this->customer_id,
            'chat_session_ulid' => $this->chat_session_ulid,
        ];
    }
}
