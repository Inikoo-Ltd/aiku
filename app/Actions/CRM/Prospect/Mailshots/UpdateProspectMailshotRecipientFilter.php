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

            // last_contacted filter
            'recipients_recipe.last_contacted' => ['sometimes', 'array'],
            'recipients_recipe.last_contacted.value' => ['sometimes', 'array'],
            'recipients_recipe.last_contacted.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.last_contacted.value.mode' => ['sometimes', 'string', 'in:one_week_ago,two_weeks_ago,three_weeks_ago,custom'],
            'recipients_recipe.last_contacted.value.custom_date' => ['sometimes', 'date'],

            // sent_email_times filter
            'recipients_recipe.sent_email_times' => ['sometimes', 'array'],
            'recipients_recipe.sent_email_times.value' => ['sometimes', 'array'],
            'recipients_recipe.sent_email_times.value.value' => ['sometimes', 'boolean'],
            'recipients_recipe.sent_email_times.value.count' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
