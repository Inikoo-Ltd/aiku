<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class DeleteWhatsappCampaign extends OrgAction
{
    use WithMarketingEditAuthorisation;

    /**
     * @throws ValidationException
     */
    public function handle(WhatsappCampaign $campaign): WhatsappCampaign
    {
        $this->assertDeletable($campaign);

        /* The picker stores recipients as soon as an audience is chosen, so a draft can
           carry rows, and whatsapp_recipients has a hard cascade that a soft delete never
           fires. Only the unclaimed ones are ours to remove; a campaign holding rows a
           delivery channel has taken is past being deletable anyway. */
        $campaign->recipients()->whereNull('whatsapp_delivery_channel_id')->delete();

        $campaign->delete();

        return $campaign;
    }

    /**
     * @throws ValidationException
     */
    protected function assertDeletable(WhatsappCampaign $campaign): void
    {
        if ($campaign->isUnsent()) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __('This campaign can no longer be deleted.'),
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign);
    }

    public function htmlResponse(WhatsappCampaign $campaign): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.marketing.whatsapp_campaigns.index', [
            'organisation' => $campaign->organisation->slug,
            'shop'         => $campaign->shop->slug,
        ]);
    }
}
