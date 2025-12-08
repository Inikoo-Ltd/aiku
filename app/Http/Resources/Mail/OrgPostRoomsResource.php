<?php

/*
 * author Arya Permana - Kirin
 * created on 02-12-2024-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2024
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
class OrgPostRoomsResource extends JsonResource
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
