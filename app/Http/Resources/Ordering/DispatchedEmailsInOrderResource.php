<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Ordering;

use App\Models\Comms\Mailshot;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

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
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $id
 *
 */
class DispatchedEmailsInOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;
        // "id\":2,\"state\":\"opened\",\"mask_as_spam\":false,\"number_email_tracking_events\":0,\"sent_at\":\"2018-06-11T09:36:11.000000Z\",\"number_reads\":1,\"number_clicks\":0}"}
        // Sample data for debugging

        return array(
            'id'                           => $this->id,
            'number_clicks'                => $this->number_clicks,
            'number_reads'                 => $this->number_reads,
            'state'                        => $mailshot->state->stateIcon()[$mailshot->state->value],
            'subject'                      => $this->subject,
            'sent_at'                      => $this->sent_at,
            'email_address'                => $this->email_address,
            'mask_as_spam'                 => $this->mask_as_spam ?
                [
                    'tooltip' => __('Spam'),
                    'icon'    => 'fal fa-dumpster',
                ] : [],
            'number_email_tracking_events' => $this->number_email_tracking_events,
            'body_preview' => $this->body_preview,

        );
    }
}
