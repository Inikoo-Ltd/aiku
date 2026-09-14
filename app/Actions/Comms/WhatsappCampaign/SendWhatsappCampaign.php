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
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;

class SendWhatsappCampaign extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignSendable;

    public function handle(WhatsappCampaign $campaign): WhatsappCampaign
    {
        $this->assertSendable($campaign);

        if (
            $campaign->state == WhatsappCampaignStateEnum::SCHEDULED
            && $campaign->scheduled_at !== null
            && $campaign->scheduled_at->lte(now())
        ) {
            $this->update($campaign, [
                'state'    => WhatsappCampaignStateEnum::READY,
                'ready_at' => now(),
            ]);

            $campaign->refresh();
        }

        if ($campaign->state != WhatsappCampaignStateEnum::READY) {
            return $campaign;
        }

        $this->update($campaign, [
            'state'            => WhatsappCampaignStateEnum::SENDING,
            'start_sending_at' => $campaign->start_sending_at ?? Carbon::now()->utc(),
        ]);

        PrepareWhatsappCampaignRecipients::dispatch($campaign);

        return $campaign->refresh();
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign);
    }
}
