<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class CancelWhatsappCampaignSchedule extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignSendable;

    public function handle(WhatsappCampaign $campaign): WhatsappCampaign
    {
        if ($campaign->state !== WhatsappCampaignStateEnum::SCHEDULED) {
            return $campaign;
        }

        /* The audience may have been emptied while the campaign sat scheduled, so the
           state it falls back to is recomputed rather than assumed to be READY. */
        $this->update($campaign, [
            'scheduled_at' => null,
            'state'        => $this->isCampaignReady($campaign)
                ? WhatsappCampaignStateEnum::READY
                : WhatsappCampaignStateEnum::IN_PROCESS,
        ]);

        return $campaign->refresh();
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign);
    }
}
