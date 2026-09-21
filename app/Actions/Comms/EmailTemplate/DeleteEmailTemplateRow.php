<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Comms\EmailTemplate;
use Lorisleiva\Actions\ActionRequest;

class DeleteEmailTemplateRow extends OrgAction
{
    use WithActionUpdate;

    public function handle(EmailTemplate $emailTemplate): EmailTemplate
    {
        return $this->update($emailTemplate, [
            'state'        => EmailTemplateStateEnum::SUSPENDED,
            'suspended_at' => now(),
        ]);
    }

    public function rules(): array
    {
        return [];
    }

    public function action(EmailTemplate $emailTemplate): EmailTemplate
    {
        $this->asAction = true;
        $this->initialisationFromGroup($emailTemplate->group, []);

        return $this->handle($emailTemplate);
    }

    public function asController(EmailTemplate $emailTemplate, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromGroup($emailTemplate->group, $request);

        return $this->handle($emailTemplate);
    }
}
