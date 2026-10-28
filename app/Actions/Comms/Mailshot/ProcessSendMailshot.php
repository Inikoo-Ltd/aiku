<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Mar 2026 15:52:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\Comms\Mailshot\Hydrators\MailshotHydrateDispatchedEmails;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Actions\Comms\Traits\WithDispatchedEmailEncryption;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use App\Models\SupplyChain\SupplierPortalContact;
use Illuminate\Database\UniqueConstraintViolationException;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSendMailshot
{
    use AsAction;
    use WithDispatchedEmailEncryption;

    public string $jobQueue = 'ses';

    /**
     * @param  list<int>  $supplierPortalContactIds  suppliers that get a shop's newsletter, always in jobs of their own
     */
    public function handle(?int $mailshotId, array $customerIds, array $supplierPortalContactIds = []): void
    {
        if (!$mailshotId) {
            return;
        }
        $mailshot = Mailshot::find($mailshotId);
        if (!$mailshot) {
            return;
        }


        $outboxId = $mailshot->outbox_id;

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($mailshot, [
            'state' => EmailDeliveryChannelStateEnum::IN_PROCESS->value,
        ]);

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                continue;
            }

            $recipientExists = $mailshot->recipients()
                ->where('recipient_id', $customer->id)
                ->where('recipient_type', class_basename($customer))
                ->exists();

            if (!$recipientExists) {
                $dispatchedEmail = StoreDispatchedEmail::run(
                    $mailshot,
                    $customer,
                    [
                        'outbox_id' => $outboxId,
                        'email_address' => $customer->email
                    ]
                );

                // Encrypt and store the dispatched email ID in the data field
                $this->encryptAndStoreDispatchedEmailId($dispatchedEmail);

                try {
                    StoreMailshotRecipient::run(
                        $mailshot,
                        [
                            'dispatched_email_id' => $dispatchedEmail->id,
                            'recipient_type'      => class_basename($customer),
                            'recipient_id'        => $customer->id,
                            'recipient_name'      => $customer->name,
                            'channel'             => $emailDeliveryChannel->id,
                        ]
                    );
                } catch (UniqueConstraintViolationException) {
                    $dispatchedEmail->delete();
                }
            }
        }

        if ($supplierPortalContactIds) {
            foreach (SupplierPortalContact::with(['profile', 'supplier'])->whereIn('id', $supplierPortalContactIds)->get() as $contact) {
                $this->storeSupplierRecipient($mailshot, $contact, $outboxId, $emailDeliveryChannel->id);
            }
        }

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $mailshot->recipients()->where('channel', $emailDeliveryChannel->id)->count(),
                'state'         => EmailDeliveryChannelStateEnum::READY->value
            ]
        );

        UpdateMailshotRecipientsStoredAt::run($mailshot);
        MailshotHydrateDispatchedEmails::dispatch($mailshot->id)->delay(now()->addSeconds(5));

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel->id)->delay(2)->onQueue($mailshot->is_second_wave ? 'ses-low' : 'ses-send');
    }

    private function storeSupplierRecipient(Mailshot $mailshot, SupplierPortalContact $contact, ?int $outboxId, int $channelId): void
    {
        if ($mailshot->recipients()->where('recipient_id', $contact->id)->where('recipient_type', class_basename($contact))->exists()) {
            return;
        }

        $dispatchedEmail = StoreDispatchedEmail::run($mailshot, $contact, [
            'outbox_id'     => $outboxId,
            'email_address' => $contact->email,
        ]);
        $this->encryptAndStoreDispatchedEmailId($dispatchedEmail);

        try {
            StoreMailshotRecipient::run($mailshot, [
                'dispatched_email_id' => $dispatchedEmail->id,
                'recipient_type'      => class_basename($contact),
                'recipient_id'        => $contact->id,
                'recipient_name'      => $contact->profile?->name ?? $contact->name ?? $contact->supplier?->contact_name ?? $contact->supplier?->name,
                'channel'             => $channelId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $dispatchedEmail->delete();
        }
    }
}
