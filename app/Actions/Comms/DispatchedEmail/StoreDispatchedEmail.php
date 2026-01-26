<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDispatchedEmails;
use App\Actions\Comms\EmailAddress\StoreEmailAddress;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDispatchedEmails;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDispatchedEmails;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OutBoxHasSubscriber;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Str;

class StoreDispatchedEmail extends OrgAction
{
    use WithNoStrictRules;


    public function handle(EmailOngoingRun|EmailBulkRun|Mailshot $parent, WebUser|Customer|Prospect|User|OutBoxHasSubscriber $recipient, array $modelData): DispatchedEmail
    {
        $outbox = $parent->outbox;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'shop_id', $parent->shop_id);
        data_set($modelData, 'outbox_id', $outbox->id);
        data_set($modelData, 'post_room_id', $outbox->post_room_id);
        data_set($modelData, 'org_post_room_id', $outbox->org_post_room_id);
        data_set($modelData, 'recipient_type', class_basename($recipient));
        data_set($modelData, 'recipient_id', $recipient->id);
        data_set($modelData, 'uuid', Str::uuid());

        $emailAddress = StoreEmailAddress::run($parent->group, Arr::pull($modelData, 'email_address'));
        data_set($modelData, 'email_address_id', $emailAddress->id);

        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail = $parent->dispatchedEmails()->create($modelData);


        GroupHydrateDispatchedEmails::dispatch($dispatchedEmail->group)->delay($this->hydratorsDelay);
        OrganisationHydrateDispatchedEmails::dispatch($dispatchedEmail->organisation)->delay($this->hydratorsDelay);
        if ($dispatchedEmail->shop_id) {
            ShopHydrateDispatchedEmails::dispatch($dispatchedEmail->shop)->delay($this->hydratorsDelay);
        }


        return $dispatchedEmail;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("mail.edit");
    }

    public function rules(): array
    {
        $rules = [
            'email_address'        => ['required', 'email'],
            'provider'             => ['required', Rule::enum(DispatchedEmailProviderEnum::class)],
            'provider_dispatch_id' => ['sometimes', 'required', 'string'],
        ];

        if (!$this->strict) {
            $rules['state']                = ['required', Rule::enum(DispatchedEmailStateEnum::class)];
            $rules['email_address']        = ['required', 'string'];
            $rules['provider_dispatch_id'] = ['sometimes', ' nullable', 'string'];

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
