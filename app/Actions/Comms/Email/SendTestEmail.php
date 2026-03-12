<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 6 Mar 2026 16:05:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\DispatchedEmail\StoreEmailTemplateDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
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

    public string $jobQueue = 'ses';

    public function handle(Mailshot|Outbox|EmailTemplate $entity, array $modelData): ?DispatchedEmail
    {
        if ($entity instanceof Mailshot) {
            $parent = $entity;
            $shop = $entity->shop;
            $sender = $entity->sender();
            $subject = $entity->subject;
            $senderName = $entity->senderName();
        } elseif ($entity instanceof Outbox) {
            $parent = $entity->emailOngoingRun;
            $shop = $entity->shop;
            $sender = $entity->emailOngoingRun->sender();
            $subject = $entity->emailOngoingRun->email->subject;
            $senderName = $entity->emailOngoingRun->senderName();
        } elseif ($entity instanceof EmailTemplate) {
            $parent = $entity;
            $shop = $entity->shop;
            $sender = $entity->sender();
            $subject = $entity->name;
            $senderName = $entity->senderName();
        } else {
            throw new \InvalidArgumentException('Invalid entity type');
        }

        $externalRecipient = StoreExternalEmailRecipient::run($shop, [
            'name' => 'Test email',
            'email' => $modelData['email']
        ]);

        if ($entity instanceof EmailTemplate) {
            $dispatchedEmail = StoreEmailTemplateDispatchedEmail::run($parent, $externalRecipient, [
                'is_test' => false,
                'email_address' => $modelData['email'],
                'provider' => DispatchedEmailProviderEnum::SES,
            ]);
        } else {
            $dispatchedEmail = StoreDispatchedEmail::run($parent, $externalRecipient, [
                'is_test' => false,
                'email_address' => $modelData['email'],
                'provider' => DispatchedEmailProviderEnum::SES,
            ]);
        }
        $dispatchedEmail->refresh();

        $this->sendEmailWithMergeTags(
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            subject: $subject,
            emailHtmlBody: $modelData['compiled_layout'],
            senderName: $senderName,
        );

        return $dispatchedEmail;
    }

    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'compiled_layout' => ['required', 'string'],
        ];
    }

    public function asControllerOutbox(Shop $shop, Outbox $outbox, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($outbox, $this->validatedData);
    }

    public function asControllerFulfillment(Organisation $organisation, Fulfilment $fulfilment, Outbox $outbox, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($outbox, $this->validatedData);
    }

    public function asControllerTemplate(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): DispatchedEmail
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate, $this->validatedData);
    }
}
