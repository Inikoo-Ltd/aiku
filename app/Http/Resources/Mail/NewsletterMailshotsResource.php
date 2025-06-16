<?php

/*
 * author Arya Permana - Kirin
 * created on 13-11-2024-11h-00m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Mail;

use App\Models\Comms\Mailshot;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $subject
 * @property mixed $number_dispatched_emails
 * @property mixed $number_estimated_dispatched_emails
 * @property mixed $number_dispatched_emails_state_delivered
 * @property mixed $number_dispatched_emails_state_opened
 * @property mixed $number_dispatched_emails_state_hard_bounce
 * @property mixed $number_dispatched_emails_state_soft_bounce
 * @property mixed $number_dispatched_emails_state_clicked
 * @property mixed $number_dispatched_emails_state_unsubscribed
 * @property mixed $number_dispatched_emails_state_spam
 * @property mixed $number_dispatched_emails_state_error
 * @property mixed $number_delivered_emails
 * @property mixed $number_spam_emails
 * @property mixed $number_opened_emails
 * @property mixed $number_clicked_emails
 * @property mixed $number_unsubscribed_emails
 * @property mixed $id
 * @property mixed $date
 * @property mixed $state
 * @property mixed $sent
 * @property mixed $delivered
 * @property mixed $dispatched_emails
 * @property mixed $hard_bounce
 * @property mixed $soft_bounce
 * @property mixed $opened
 * @property mixed $clicked
 * @property mixed $spam
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $shop_slug
 * @property mixed $number_deliveries_success
 * @property mixed $number_try_send_success
 * @property mixed $number_delivered_open_success
 * @property mixed $unsubscribed
 */
class NewsletterMailshotsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;

        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'date'                      => Carbon::parse($this->date)->format('d M Y, H:i'),
            'subject'                   => $this->subject,
            'state'                     => $this->state,
            'state_label'               => $mailshot->state->labels()[$mailshot->state->value],
            'state_icon'                => $mailshot->state->stateIcon()[$mailshot->state->value],
            'number_deliveries_success' => $this->number_deliveries_success,
            'number_try_send_success'   => $this->number_try_send_success,
            'delivered'                 => percentage($this->delivered, $this->dispatched_emails),
            'hard_bounce'               => percentage($this->hard_bounce, $this->dispatched_emails),
            'soft_bounce'               => percentage($this->soft_bounce, $this->dispatched_emails),
            'opened'                    => percentage($this->number_delivered_open_success, $this->number_deliveries_success),
            'clicked'                   => percentage($this->clicked, $this->number_delivered_open_success),
            'spam'                      => percentage($this->spam, $this->number_deliveries_success),
            'unsubscribed'              => percentage($this->unsubscribed, $this->number_delivered_open_success),
            'organisation_name'         => $this->organisation_name,
            'organisation_slug'         => $this->organisation_slug,
            'shop_name'                 => $this->shop_name,
            'shop_slug'                 => $this->shop_slug,


        ];
    }
}
