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

class ScheduleWhatsappCampaign extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignSendable;

    public function handle(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        $this->assertSendable($campaign);

        $this->update($campaign, array_merge($modelData, [
            'state' => WhatsappCampaignStateEnum::SCHEDULED,
        ]));

        return $campaign->refresh();
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign, $this->validatedData);
    }
}
