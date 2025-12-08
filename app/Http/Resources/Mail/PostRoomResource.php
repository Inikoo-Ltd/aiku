<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
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
class PostRoomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'number_mailshots' => $this->number_mailshots,
            'dispatched_emails_lw' => $this->dispatched_emails_lw,
            'opened_emails_lw' => $this->opened_emails_lw,
            'runs' => $this->runs,
            'unsubscribed_lw' => $this->unsubscribed_lw,
        ];
    }
}
