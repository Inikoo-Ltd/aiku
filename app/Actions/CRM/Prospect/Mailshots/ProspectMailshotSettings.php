<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Feb 2025 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Comms\SenderEmailResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ProspectMailshotSettings extends OrgAction
{
    use WithCRMAuthorisation;

    public function handle(Shop $shop): array
    {
        return [
            'senderEmail' => $shop->prospects_sender_email_id
                ? SenderEmailResource::make($shop->prospectsSenderEmail)->getArray()
                : null,
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
