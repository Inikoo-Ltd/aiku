<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 19 Dec 2024 15:05:54 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;

class UnsubscribeMailshot
{
    use WithActionUpdate;

    public function handle(DispatchedEmail $dispatchedEmail, ActionRequest $request, ?string $tag = null): array
    {

        /** @var Customer|Prospect $recipient */
        $recipient = null;

        if ($tag === 'prospect') {
            $prospectDispatchedEmail = DB::table('prospect_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($prospectDispatchedEmail) {
                $recipient = Prospect::find($prospectDispatchedEmail->prospect_id);
            } else {
                abort(404, 'Prospect recipient not found');
            }
        } else {
            // Default to customer lookup
            $customerDispatchedEmail = DB::table('customer_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($customerDispatchedEmail) {
                $recipient = Customer::find($customerDispatchedEmail->customer_id);
            } else {
                abort(404, 'Customer recipient not found');
            }
        }

        if ($recipient instanceof Prospect) {
            UpdateProspectEmailUnsubscribed::run($recipient, now());
        }

        if ($recipient instanceof Customer) {

            $hasMailshot = DB::table('mailshot_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($hasMailshot) {
                $mailshot = Mailshot::find($hasMailshot->mailshot_id);

                if ($mailshot->shop_id == $recipient->shop_id) {
                    $modelData = match ($mailshot->type) {
                        MailshotTypeEnum::NEWSLETTER => [
                            'is_subscribed_to_newsletter' => false,
                        ],
                        MailshotTypeEnum::MARKETING => [
                            'is_subscribed_to_marketing' => false,
                        ],
                        default => []
                    };

                    $customerComms = $recipient->comms;
                    UpdateCustomerComms::run($customerComms, $modelData, false);
                }
            }


            $hasEmailBulkRun = DB::table('email_bulk_run_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($hasEmailBulkRun) {
                $emailBulkRun = EmailBulkRun::find($hasEmailBulkRun->email_bulk_run_id);

                if ($emailBulkRun->shop_id == $recipient->shop_id) {
                    $modelData = match ($emailBulkRun->outbox->code) {
                        OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION => [
                            'is_subscribed_to_price_change_notification' => false,
                        ],
                        OutboxCodeEnum::BASKET_LOW_STOCK => [
                            'is_subscribed_to_basket_low_stock' => false,
                        ],
                        OutboxCodeEnum::REORDER_REMINDER, OutboxCodeEnum::REORDER_REMINDER_2ND, OutboxCodeEnum::REORDER_REMINDER_3RD => [
                            'is_subscribed_to_reorder_reminder' => false,
                        ],
                        default => []
                    };

                    $customerComms = $recipient->comms;
                    UpdateCustomerComms::run($customerComms, $modelData, false);
                }
            }
        }

        UpdateDispatchedEmail::run(
            $dispatchedEmail,
            [
                'state'                => DispatchedEmailStateEnum::UNSUBSCRIBED,
                'provoked_unsubscribe' => true

            ]
        );

        $eventData = [
            'type'            => EmailTrackingEventTypeEnum::UNSUBSCRIBED,
            'data'            => [
                'ipAddress' => $request->ip(),
                'userAgent' => $request->userAgent()
            ]
        ];


        $dispatchedEmail->emailTrackingEvents()->create($eventData);

        $dispatchedEmail->refresh();

        return [
            'id' => $dispatchedEmail->id,
            'tag' => $tag,
        ];
    }

    public function asController(string $encryptedDispatchedEmailID, ActionRequest $request): array
    {
        $dispatchedEmailID = Crypt::decryptString($encryptedDispatchedEmailID);
        $dispatchedEmail   = DispatchedEmail::findOrFail($dispatchedEmailID);

        $tag = $request->get('tag');

        return $this->handle($dispatchedEmail, $request, $tag);
    }

    public function jsonResponse(array $data): array
    {
        $dispatchedEmailId = $data['id'];
        $tag = $data['tag'];

        $dispatchedEmail = DispatchedEmail::findOrFail($dispatchedEmailId);
        $recipientName = '';

        if ($tag === 'prospect') {
            $prospectDispatchedEmail = DB::table('prospect_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();
            if ($prospectDispatchedEmail) {
                $prospect = Prospect::find($prospectDispatchedEmail->prospect_id);
                $recipientName = $prospect->name;
            }
        } else {
            // Default to customer lookup
            $customerDispatchedEmail = DB::table('customer_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();
            if ($customerDispatchedEmail) {
                $customer = Customer::find($customerDispatchedEmail->customer_id);
                $recipientName = $customer->contact_name;
            }
        }

        return [
            'api_response_status' => 200,
            'api_response_data'   => [
                'recipient_email' => $dispatchedEmail->emailAddress?->email,
                'recipient_name'  => $recipientName,
            ]
        ];
    }
}
