<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 11:13:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms\MailshotRecipient;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $state
 * @property mixed $sent_at
 * @property mixed $email_address
 * @property mixed $customer_name
 */
class MailshotRecipientsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                           => $this->id,
            'recipient_type'               => $this->recipient_type,
            'state'                        => DispatchedEmailStateEnum::stateIcon()[$this->state],
            'email_address'                => $this->email_address,
            'sent_at'                      => $this->sent_at,
            'customer_name'                => $this->customer_name,
        ];
    }
}
