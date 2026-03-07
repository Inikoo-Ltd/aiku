<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 2 Mar 2026 14:25:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Comms\SenderEmailResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

//  TODO: Need make sure this setting needed or not
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
