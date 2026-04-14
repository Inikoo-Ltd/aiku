<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 2 Mar 2026 10:49:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\CRM;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $state
 * @property mixed $sent_at
 * @property mixed $email_address
 * @property mixed $prospect_name
 */
class ProspectMailshotRecipientsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                           => $this->id,
            'recipient_type'               => $this->recipient_type,
            'state'                        => DispatchedEmailStateEnum::stateIcon()[$this->state],
            'email_address'                => $this->email_address,
            'sent_at'                      => $this->sent_at,
            'prospect_name'                => $this->prospect_name,
        ];
    }
}
