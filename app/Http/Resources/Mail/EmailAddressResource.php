<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_outboxes
 * @property int $number_mailshots
 * @property int $number_dispatched_emails
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class EmailAddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'number_marketing_dispatches' => $this->number_marketing_dispatches,
            'number_transactional_dispatches' => $this->number_transactional_dispatches,
            'last_marketing_dispatch_at' => $this->last_marketing_dispatch_at,
            'last_transactional_dispatch_at' => $this->last_transactional_dispatch_at,
            'soft_bounced_at' => $this->soft_bounced_at,
            'hard_bounced_at' => $this->hard_bounced_at,
        ];
    }
}
