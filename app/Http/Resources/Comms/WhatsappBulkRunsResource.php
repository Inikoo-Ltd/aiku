<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Models\Comms\WhatsappBulkRun;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsappBulkRun
 */
class WhatsappBulkRunsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'state'            => $this->state->value,
            'state_label'      => WhatsappBulkRunStateEnum::labels()[$this->state->value],
            'recipients_count' => $this->recipients_count,
            'number_sent'      => $this->number_sent,
            'number_failed'    => $this->number_failed,
            'created_at'       => $this->created_at,
            'sent_at'          => $this->sent_at,
        ];
    }
}
