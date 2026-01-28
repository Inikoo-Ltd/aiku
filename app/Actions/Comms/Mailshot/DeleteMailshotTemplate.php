<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 23 Jan 2026 13:46:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateEmailTemplates;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Lorisleiva\Actions\ActionRequest;

class DeleteMailshotTemplate extends OrgAction
{
    public function handle(EmailTemplate $emailTemplate): bool
    {
        $result = $emailTemplate->delete();

        if ($result) {
            ShopHydrateEmailTemplates::dispatch($emailTemplate->shop);
        }

        return $result;
    }

    public function asController(Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): bool
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate);
    }
}
