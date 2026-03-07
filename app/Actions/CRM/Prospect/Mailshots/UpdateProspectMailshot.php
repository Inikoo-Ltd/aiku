<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 28 Feb 2025 14:26:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Comms\Mailshot;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateProspectMailshot extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $mailshot = $this->update($mailshot, $modelData, ['data']);

        // update subject if changed
        if ($mailshot->wasChanged('subject') && $mailshot->email) {
            $mailshot->email->update(['subject' => $mailshot->subject]);
        }

        // TODO: check and make sure later
        // if ($mailshot->wasChanged('state')) {
        //     GroupHydrateMailshots::dispatch($mailshot->group)->delay($this->hydratorsDelay);
        //     OrganisationHydrateMailshots::dispatch($mailshot->organisation)->delay($this->hydratorsDelay);
        //     OutboxHydrateMailshots::dispatch($mailshot->outbox)->delay($this->hydratorsDelay);
        //     ShopHydrateMailshots::dispatch($mailshot->shop)->delay($this->hydratorsDelay);
        // }

        return $mailshot;
    }



    public function rules(): array
    {
        $rules = [
            'subject'           => ['sometimes', 'string', 'max:255'],
            'state'             => ['sometimes', Rule::enum(MailshotStateEnum::class)],
            'recipients_recipe' => ['sometimes', 'array']
        ];

        if (!$this->strict) {
            $rules['date']             = ['nullable', 'date'];
            $rules['ready_at']         = ['nullable', 'date'];
            $rules['scheduled_at']     = ['nullable', 'date'];
            $rules['start_sending_at'] = ['nullable', 'date'];
            $rules['sent_at']          = ['nullable', 'date'];
            $rules['stopped_at']       = ['nullable', 'date'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(Mailshot $mailshot, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Mailshot
    {
        $this->strict = $strict;
        if (!$audit) {
            Mailshot::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($mailshot->shop, $modelData);

        return $this->handle($mailshot, $this->validatedData);
    }
    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }


    public function jsonResponse(Mailshot $mailshot): MailshotResource
    {
        return new MailshotResource($mailshot);
    }
}
