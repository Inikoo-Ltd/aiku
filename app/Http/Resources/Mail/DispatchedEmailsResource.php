<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Mail;

use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
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
 * @property mixed $customer_name
 * @property mixed $order_slug
 * @property mixed $customer_slug
 * @property mixed $fulfilment_customer_slug
 *
 */
class DispatchedEmailsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;

        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;
        $order = $this->order_id ? Order::find($this->order_id) : null;

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
            'organisation_name'            => $this->organisation_name,
            'organisation_slug'            => $this->organisation_slug,
            'customer_name'                => $customer?->name ?? null,
            'order_slug'                   => $order?->slug ?? null,
            'customer_slug'                => $customer?->slug ?? null,
            'shop_slug'                    => $customer?->shop?->slug ?? null,
            'fulfilment_customer_slug'     => $this->fulfilment_customer_slug,
        );
    }
}
