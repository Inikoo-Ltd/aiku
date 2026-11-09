<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Ordering;

use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
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
 * @property ?string $email_type
 * @property ?int $outbox_id
 *
 */
class DispatchedEmailsInOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;

        return array(
            'id'                           => $this->id,
            'number_clicks'                => $this->number_clicks,
            'number_reads'                 => $this->number_reads,
            'type'                         => $this->typeIcon(),
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
            'body_preview' => $this->is_body_encoded ? $this->decodeBodySafely($this->body_preview) : $this->body_preview,

        );
    }

    /** The on-hold emails share one outbox, so their type comes from what the sender recorded; outboxes are looked up, not joined, because archived emails are read from a database without them (HELP-4580) */
    private function typeIcon(): array
    {
        return match ($this->email_type ?? Outbox::find($this->outbox_id)?->code?->value) {
            'notice' => ['tooltip' => __('Order on hold, payment needed'), 'icon' => 'fal fa-hand-paper'],
            'reminder' => ['tooltip' => __('Payment reminder'), 'icon' => 'fal fa-bell'],
            'final_reminder' => ['tooltip' => __('Final payment reminder'), 'icon' => 'fal fa-hourglass-end'],
            'no_delivery' => ['tooltip' => __('Order on hold, no delivery to this address'), 'icon' => 'fal fa-ban'],
            'channel_order_on_hold' => ['tooltip' => __('Order on hold'), 'icon' => 'fal fa-hand-paper'],
            'order_confirmation' => ['tooltip' => __('Order confirmation'), 'icon' => 'fal fa-shopping-cart'],
            'delivery_confirmation' => ['tooltip' => __('Delivery confirmation'), 'icon' => 'fal fa-truck'],
            'pre_order_update' => ['tooltip' => __('Pre-order update'), 'icon' => 'fal fa-calendar-alt'],
            'send_purchase_order_to_supplier' => ['tooltip' => __('Purchase order'), 'icon' => 'fal fa-clipboard-list'],
            'send_invoice_to_customer' => ['tooltip' => __('Invoice'), 'icon' => 'fal fa-file-invoice-dollar'],
            default => ['tooltip' => __('Email'), 'icon' => 'fal fa-envelope'],
        };
    }

    private function decodeBodySafely($body): string
    {
        try {
            $decoded = base64_decode($body, true);
            if ($decoded === false) {
                return '[Decode Error]';
            }

            // Check if the decoded string is valid UTF-8
            if (!mb_check_encoding($decoded, 'UTF-8')) {
                // Try to fix encoding issues
                $decoded = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
                if (!mb_check_encoding($decoded, 'UTF-8')) {
                    return '[Encoding Error]';
                }
            }

            return $decoded;
        } catch (\Exception $e) {
            return '[Decode Error]';
        }
    }
}
