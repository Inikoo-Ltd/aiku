<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;

class UpdateProspectMailshotRecipientFilter extends OrgAction
{
    use WithActionUpdate;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $this->update($mailshot, $modelData);

        return $mailshot;
    }

    public function rules(): array
    {
        return [
            'recipients_recipe' => ['required', 'array'],

            // by_all_prospects filter
            'recipients_recipe.all_prospects' => ['sometimes', 'array'],
            'recipients_recipe.all_prospects.value' => ['sometimes', 'boolean'],

            // never_contacted filter
            'recipients_recipe.never_contacted' => ['sometimes', 'array'],
            'recipients_recipe.never_contacted.value' => ['sometimes', 'array'],
            'recipients_recipe.never_contacted.value.value' => ['sometimes', 'boolean'],

            // last_contacted_3_weeks_ago filter
            'recipients_recipe.last_contacted_3_weeks_ago' => ['sometimes', 'array'],
            'recipients_recipe.last_contacted_3_weeks_ago.value' => ['sometimes', 'array'],
            'recipients_recipe.last_contacted_3_weeks_ago.value.value' => ['sometimes', 'boolean'],

            // sent_email_3_times filter
            'recipients_recipe.sent_email_3_times' => ['sometimes', 'array'],
            'recipients_recipe.sent_email_3_times.value' => ['sometimes', 'array'],
            'recipients_recipe.sent_email_3_times.value.value' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
