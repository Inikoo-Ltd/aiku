<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 22 Jan 2026 11:59:54 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\InertiaAction;
use App\Models\Comms\EmailTemplate;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetEmailTemplateLayout extends InertiaAction
{
    use AsController;

    public function handle(EmailTemplate $emailTemplate): array
    {
        return $emailTemplate->layout;
    }

    public function authorize(ActionRequest $request): bool
    {
        // todo need to change this
        return true;
    }

    public function asController(EmailTemplate $emailTemplate): array
    {
        return $this->handle($emailTemplate);
    }
}
