<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Mail;

use App\Enums\Comms\Outbox\OutboxTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $data
 * @property string $name
 * @property OutboxTypeEnum $type
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property int $number_mailshots
 * @property int $dispatched_emails_lw
 * @property int $opened_emails_lw
 * @property int $unsubscribed_lw
 * @property int $runs
 */
class OutboxHasSubscribersResource extends JsonResource
{
    public function toArray($request): array
    {
        $res = $this->user ? [
            'username' => $this->user->username,
            'contact_name' => $this->user->contact_name,
            'email' => $this->email,
        ] : [
            'external_email' => $this->external_email,
        ];

        return $res;
    }
}
