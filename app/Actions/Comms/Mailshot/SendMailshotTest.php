<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Oct 2023 16:11:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class SendMailshotTest extends OrgAction
{
    use AsCommand;
    use AsAction;
    use WithSendBulkEmails;

    public function handle(Outbox $outbox, array $modelData): Collection
    {
        $dispatchedEmails = [];

        $parent = StoreMailshot::run($outbox, [
            'subject' => $outbox->emailOngoingRun->email->subject,
            'email_id' => $outbox->emailOngoingRun->email_id,
            'type' => MailshotTypeEnum::MARKETING,
            'state' => MailshotStateEnum::IN_PROCESS
        ]);
        $recipients = Customer::whereIn('email', Arr::get($modelData, 'emails', []))->get();

        foreach ($recipients as $recipient) {
            $dispatchedEmail = StoreDispatchedEmail::run($parent, $recipient, [
                'is_test'   => true,
                'outbox_id' => Outbox::where('type', OutboxTypeEnum::TEST)->pluck('id')->first(),
                'email_address' => $recipient->email,
                'provider' => DispatchedEmailProviderEnum::SES
            ]);
            $dispatchedEmail->refresh();

            $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;

            $unsubscribeUrl = route('iris.unsubscribe.show', $dispatchedEmail->id);

            $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $parent->sender(),
                $parent->subject,
                $emailHtmlBody,
                $unsubscribeUrl,
            );

            $dispatchedEmails[] = $dispatchedEmail;
        }

        return collect($dispatchedEmails);
    }

    public function jsonResponse($dispatchedEmails): AnonymousResourceCollection
    {
        return DispatchedEmailsResource::collection($dispatchedEmails);
    }

    public function rules(): array
    {
        return [
            'emails'   => ['required', 'array'],
            'emails.*' => 'required|email:rfc,dns'
        ];
    }

    public function asController(Shop $shop, Outbox $outbox, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($outbox, $this->validatedData);
    }
}
