<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 11:13:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms\MailshotRecipient;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Comms\Mailshot;

/**
 * @property string $state
 * @property string $number_clicks
 * @property string $number_reads
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $sent_at
 * @property mixed $email_address
 * @property mixed $mask_as_spam
 * @property mixed $number_email_tracking_events
 * @property mixed $recipient_type
 * @property mixed $channel
 * @property mixed $subject
 * @property mixed $body_preview
 * @property mixed $is_body_encoded
 * @property mixed $id
 */
class MailshotRecipientsResource extends JsonResource
{
    public function toArray($request): array
    {

        /** @var Mailshot $mailshot */
        $mailshot = $this;
        return [
            'id'                           => $this->id,
            'recipient_type'               => $this->recipient_type,
            // 'state'                        => $mailshot->state->stateIcon()[$mailshot->state->value],
            'subject'                      => $this->subject,
            'email_address'                => $this->email_address,
            'sent_at'                      => $this->sent_at,
            'created_at'                   => $this->created_at,
            'updated_at'                   => $this->updated_at,
            'number_clicks'                => $this->number_clicks,
            'number_reads'                 => $this->number_reads,
            'number_email_tracking_events' => $this->number_email_tracking_events,
            'mask_as_spam'                 => $this->mask_as_spam ?
                [
                    'tooltip' => __('Spam'),
                    'icon'    => 'fal fa-dumpster',
                ] : [],
            'body_preview'                 => $this->is_body_encoded ? base64_decode($this->body_preview) : $this->body_preview,
            'customer_name'                => $this->customer_name,
        ];
    }
}
