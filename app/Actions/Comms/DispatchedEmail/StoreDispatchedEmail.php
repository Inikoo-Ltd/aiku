<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Actions\Comms\EmailAddress\StoreEmailAddress;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateDispatchedEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\ExternalEmailRecipient;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreDispatchedEmail extends OrgAction
{
    use WithNoStrictRules;


    public function handle(EmailOngoingRun|EmailBulkRun|Mailshot|EmailTemplate $parent, WebUser|Customer|Prospect|User|OutBoxHasSubscriber|ExternalEmailRecipient $recipient, array $modelData, bool $isTest = false): DispatchedEmail
    {
        if (!$parent instanceof EmailTemplate) {
            $outbox = $parent->outbox;
            data_set($modelData, 'outbox_id', $outbox->id);
        }

        data_set($modelData, 'recipient_type', class_basename($recipient));
        data_set($modelData, 'recipient_id', $recipient->id);
        data_set($modelData, 'uuid', Str::uuid());

        $emailAddress = StoreEmailAddress::run($parent->group, Arr::pull($modelData, 'email_address'));
        data_set($modelData, 'email_address_id', $emailAddress->id);

        /** @var DispatchedEmail $dispatchedEmail */
        if (!$parent instanceof EmailTemplate) {
            $dispatchedEmail = $parent->dispatchedEmails()->create($modelData);
        } else {
            $dispatchedEmail = DispatchedEmail::create($modelData);
        }

        if (!$isTest) {
            OutboxHydrateDispatchedEmails::dispatch($dispatchedEmail->outbox_id)->delay(10);
        }

        return $dispatchedEmail;
    }

    public function rules(): array
    {
        $rules = [
            'email_address' => ['required', 'email'],
            'provider'      => ['required', Rule::enum(DispatchedEmailProviderEnum::class)],
        ];

        if (!$this->strict) {
            $rules['state']         = ['required', Rule::enum(DispatchedEmailStateEnum::class)];
            $rules['email_address'] = ['required', 'string'];

            $rules['sent_at']          = ['sometimes', 'nullable', 'date'];
            $rules['first_read_at']    = ['sometimes', 'nullable', 'date'];
            $rules['last_read_at']     = ['sometimes', 'nullable', 'date'];
            $rules['first_clicked_at'] = ['sometimes', 'nullable', 'date'];
            $rules['last_clicked_at']  = ['sometimes', 'nullable', 'date'];
            $rules['number_reads']     = ['sometimes', 'integer'];
            $rules['number_clicks']    = ['sometimes', 'integer'];


            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(EmailOngoingRun|EmailBulkRun|Mailshot $parent, Customer|Prospect|User $recipient, array $modelData, int $hydratorsDelay = 0, bool $strict = true): DispatchedEmail
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($parent, $recipient, $this->validatedData);
    }
}
