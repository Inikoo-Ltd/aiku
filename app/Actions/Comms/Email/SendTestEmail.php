<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 6 Mar 2026 16:05:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\TestEmailRecipient\StoreTestEmailRecipient;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class SendTestEmail extends OrgAction
{
    use WithSendBulkEmails;

    /**
     * @throws \Throwable
     */
    public function handle(Mailshot|Outbox|EmailTemplate $entity, array $modelData): ?DispatchedEmail
    {
        if ($entity instanceof Mailshot) {
            $parent     = $entity;
            $shop       = $entity->shop;
            $sender     = $entity->sender();
            $subject    = $entity->subject;
            $senderName = $entity->senderName();
        } elseif ($entity instanceof Outbox) {
            $parent     = $entity->emailOngoingRun;
            $shop       = $entity->shop;
            $sender     = $entity->emailOngoingRun->sender();
            $subject    = $entity->emailOngoingRun->email->subject;
            $senderName = $entity->emailOngoingRun->senderName();
        } elseif ($entity instanceof EmailTemplate) {
            $parent     = $entity;
            $shop       = $entity->shop;
            $sender     = $entity->sender();
            $subject    = $entity->name;
            $senderName = $entity->senderName();
        } else {
            throw new \InvalidArgumentException('Invalid entity type');
        }


        $email = $modelData['email'];

        $testEmailRecipient = $shop->testEmailRecipients()->where('email', $email)->first();

        if (!$testEmailRecipient) {
            $testEmailRecipient = StoreTestEmailRecipient::make()->action($shop, [
                'name'  => 'Mr/Miss Tester',
                'email' => $email,
            ]);
        }


        $dispatchedEmail = StoreDispatchedEmail::run(
            parent: $parent,
            recipient: $testEmailRecipient,
            modelData: [
                'email_address' => $modelData['email'],
            ],
            isTest: true
        );

        $dispatchedEmail->refresh();

        $this->sendEmailWithMergeTags(
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            subject: $subject,
            emailHtmlBody: $modelData['compiled_layout'],
            senderName: $senderName,
            isTest: true
        );

        return $dispatchedEmail;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'email'           => ['required', 'email'],
            'compiled_layout' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asControllerOutbox(Shop $shop, Outbox $outbox, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($outbox, $this->validatedData);
    }

    /**
     * @throws \Throwable
     * @noinspection PhpUnusedParameterInspection
     */
    public function asControllerFulfillment(Organisation $organisation, Fulfilment $fulfilment, Outbox $outbox, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($outbox, $this->validatedData);
    }

    /**
     * @throws \Throwable
     * @noinspection PhpUnusedParameterInspection
     */
    public function asControllerTemplate(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate, $this->validatedData);
    }
}
