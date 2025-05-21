<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Mail;

use App\Models\Comms\Mailshot;
use Illuminate\Http\Resources\Json\JsonResource;

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
class DispatchedEmailsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;

        return array(
            'id'                           => $this->id,
            'number_clicks'                => $this->number_clicks,
            'number_reads'                 => $this->number_reads,
            'state'                        => $mailshot->state->stateIcon()[$mailshot->state->value],
            'created_at'                   => $this->created_at,
            'updated_at'                   => $this->updated_at,
            'sent_at'                      => $this->sent_at,
            'email_address'                => $this->email_address,
            'mask_as_spam'                 => $this->mask_as_spam ?
                [
                    'tooltip' => __('Spam'),
                    'icon'    => 'fal fa-dumpster',
                ] : [],
            'number_email_tracking_events' => $this->number_email_tracking_events,
            'shop_code'                    => $this->shop_code,
            'shop_name'                    => $this->shop_name,
            'organisation_name'            => $this->organisation_name,
            'organisation_slug'            => $this->organisation_slug,
        );
    }
}
