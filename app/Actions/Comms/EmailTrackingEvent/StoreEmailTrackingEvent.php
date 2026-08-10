<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTrackingEvent;

use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateClicks;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateEmailTracking;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateReads;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Actions\CRM\TrafficSource\RecordTrafficSourceClick;
use App\Actions\CRM\TrafficSource\RecordTrafficSourceVisit;
use Illuminate\Support\Arr;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailTrackingEvent;
use Illuminate\Validation\Rule;

class StoreEmailTrackingEvent extends OrgAction
{
    use WithNoStrictRules;

    public function handle(DispatchedEmail $dispatchedEmail, array $modelData): EmailTrackingEvent
    {
        /** @var EmailTrackingEvent $emailTrackingEvent */
        $emailTrackingEvent = $dispatchedEmail->emailTrackingEvents()->create($modelData);

        DispatchedEmailHydrateEmailTracking::run($dispatchedEmail);
        if ($emailTrackingEvent->type == EmailTrackingEventTypeEnum::CLICKED) {
            DispatchedEmailHydrateClicks::run($dispatchedEmail);

            /* A mailshot click is a newsletter touch. Some of what else we send is marketing too - a
               reorder reminder, an abandoned basket chase, a back-in-stock notice - and a click on one
               of those brought the customer back just as surely; those are credited to the automated
               emails channel, listed in config('marketing.attributed_outbox_codes').

               Everything else stays uncredited. Order confirmations, dispatch notices, invoices and
               password reminders reach the same CLICKED state, and crediting them would hand marketing
               a share of every engaged customer's revenue for clicks that are service, not
               acquisition. */
            $mailshot   = $dispatchedEmail->sentMailshot;
            $outboxCode = $mailshot ? null : $this->attributedOutboxCode($dispatchedEmail);

            /* A click on an email is a visit, and the only place it can be counted: by the time the
               reader lands on the storefront the referrer is their webmail, which we ignore. Counted
               per click rather than per touch, since opening the same link twice really is two
               visits even though it is one touch. */
            if ($mailshot || $outboxCode) {
                $channel = $mailshot
                    ? TrafficSourcesTypeEnum::fromMailshotType($mailshot->type?->value ?? $mailshot->type)
                    : TrafficSourcesTypeEnum::EMAIL_AUTOMATED;

                /* Email needs the fraud record more than the storefront does: security scanners
                   click every link in every message, and without the user agent on record they are
                   indistinguishable from readers - 40 of the first day's 46 newsletter clicks were
                   Microsoft's scanner. So nothing is counted here. The burst counter is written now,
                   and the visit and the click row are decided two minutes later, once the burst is
                   over and the counter can tell a scanner from a reader. Guarded on ipAddress so
                   historical Aurora imports, which replay these events without one, count nothing. */
                $shop        = $dispatchedEmail->outbox?->shop;
                $ip          = Arr::get($emailTrackingEvent->data, 'ipAddress');
                $campaignRef = $mailshot
                    ? ($channel === TrafficSourcesTypeEnum::NEWSLETTER
                        ? RecordEmailClickTouchpoint::CAMPAIGN_REF_PREFIX
                        : RecordEmailClickTouchpoint::MARKETING_CAMPAIGN_REF_PREFIX).$mailshot->id
                    : RecordEmailClickTouchpoint::OUTBOX_CAMPAIGN_REF_PREFIX.$outboxCode;

                if ($shop && $ip && $channel) {
                    RecordTrafficSourceClick::countScannerClick($ip, $campaignRef);

                    RecordTrafficSourceClick::dispatch([
                        'shop_id'       => $shop->id,
                        'website_id'    => $shop->website?->id,
                        'type'          => $channel->value,
                        'campaign_ref'  => $campaignRef,
                        'click_id'      => null,
                        'ip'            => $ip,
                        'country_code'  => null,
                        'user_agent'    => Arr::get($emailTrackingEvent->data, 'userAgent'),
                        'url'           => Arr::get($emailTrackingEvent->data, 'l'),
                        'is_repeat'     => false,
                        'check_scanner' => true,
                        'count_visit'   => true,
                    ])->delay(now()->addMinutes(2));
                } elseif ($channel && ($emailTrackingEvent->created_at ?? now())->gt(now()->subHour())) {
                    /* No IP means no scanner check is possible; a live event without one still
                       deserves its visit. The recency guard keeps Aurora replays of historic
                       clicks, which also carry no IP, from counting as today's traffic. */
                    RecordTrafficSourceVisit::run($dispatchedEmail->outbox?->shop_id, $channel);
                }
            }

            if ($mailshot || $outboxCode) {
                $clickedAt = $emailTrackingEvent->created_at ?? now();

                /* Delayed for the same reason as the click row: the touch decision needs the scanner
                   burst to be over. Two minutes is invisible to attribution and conclusive for a
                   burst that finishes in under thirty seconds. */
                $touchDelay = $ip ? now()->addMinutes(2) : null;

                foreach ($dispatchedEmail->customers as $customer) {
                    RecordEmailClickTouchpoint::dispatch($customer, $clickedAt, $mailshot, $outboxCode, $ip)->delay($touchDelay);
                }

                foreach ($dispatchedEmail->prospects as $prospect) {
                    RecordEmailClickTouchpoint::dispatch($prospect, $clickedAt, $mailshot, $outboxCode, $ip)->delay($touchDelay);
                }
            }
        } elseif ($emailTrackingEvent->type == EmailTrackingEventTypeEnum::OPENED) {
            DispatchedEmailHydrateReads::run($dispatchedEmail);
        }


        return $emailTrackingEvent;
    }

    /**
     * The outbox code behind this send, when it is one we count as marketing.
     */
    private function attributedOutboxCode(DispatchedEmail $dispatchedEmail): ?string
    {
        $code = $dispatchedEmail->outbox?->code;
        $code = $code instanceof \BackedEnum ? $code->value : $code;

        return in_array($code, (array) config('marketing.attributed_outbox_codes', []), true)
            ? $code
            : null;
    }

    public function rules(): array
    {
        $rules = [
            'type'       => ['required', Rule::enum(EmailTrackingEventTypeEnum::class)],
            'data'       => ['sometimes', 'array'],
            'created_at' => ['required', 'date'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(DispatchedEmail $dispatchedEmail, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailTrackingEvent
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($dispatchedEmail->outbox->organisation, $modelData);

        return $this->handle($dispatchedEmail, $this->validatedData);
    }
}
