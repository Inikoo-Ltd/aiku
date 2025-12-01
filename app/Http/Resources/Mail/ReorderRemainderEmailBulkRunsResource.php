<?php

/*
 * author Eka Yudinata - Aiku
 * created on 01-12-2024-23h-18m
 * github: https://github.com/ekayudinatha
 * copyright 2024
*/

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property integer $number_outboxes
 * @property integer $number_mailshots
 * @property integer $number_dispatched_emails
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 *
 */
class ReorderRemainderEmailBulkRunsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'subject'   => $this->subject,
            'state_icon'     => $this->state->stateIcon()[$this->state->value],
            'number_dispatched_emails' => $this->number_dispatched_emails,
            'number_dispatched_emails_state_sent' => $this->number_dispatched_emails_state_sent,
            'number_dispatched_emails_state_delivered' => $this->number_dispatched_emails_state_delivered,
            'number_dispatched_emails_state_hard_bounce' => $this->number_dispatched_emails_state_hard_bounce,
            'number_dispatched_emails_state_soft_bounce' => $this->number_dispatched_emails_state_soft_bounce,
            'number_dispatched_emails_state_opened' => $this->number_dispatched_emails_state_opened,
            'number_dispatched_emails_state_clicked' => $this->number_dispatched_emails_state_clicked,
            'number_dispatched_emails_state_spam' => $this->number_dispatched_emails_state_spam,

        ];
    }
}
