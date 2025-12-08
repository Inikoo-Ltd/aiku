<?php

/*
 * author Arya Permana - Kirin
 * created on 13-11-2024-11h-36m
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
 */
class MarketingMailshotsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Mailshot $mailshot */
        $mailshot = $this;

        return [
            'id' => $mailshot->id,
            'slug' => $mailshot->slug,
            'date' => Carbon::parse($mailshot->date)->format('d F Y, H:i'),
            'subject' => $mailshot->subject,
            'state' => $mailshot->state,
            'state_label' => $mailshot->state->labels()[$mailshot->state->value],
            'state_icon' => $mailshot->state->stateIcon()[$mailshot->state->value],
            'sent' => $mailshot->sent,
            'delivered' => percentage($mailshot->delivered, $mailshot->dispatched_emails),
            'hard_bounce' => percentage($mailshot->hard_bounce, $mailshot->dispatched_emails),
            'soft_bounce' => percentage($mailshot->soft_bounce, $mailshot->dispatched_emails),
            'opened' => percentage($mailshot->opened, $mailshot->delivered),
            'clicked' => percentage($mailshot->clicked, $mailshot->delivered),
            'spam' => percentage($mailshot->spam, $mailshot->delivered),
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name' => $this->shop_name,
            'shop_slug' => $this->shop_slug,

        ];
    }
}
