<?php

namespace App\Actions\Traits;

use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappSubscriber;
use Lorisleiva\Actions\ActionRequest;

trait WithWhatsappCampaignsSubNavigation
{
    /**
     * Both counts are live rather than hydrated: a shop holds a few hundred subscribers and a
     * handful of campaigns, so a count on each tab is cheaper than keeping a stats column in step.
     * Soft deleted rows are left out by the models own scopes.
     */
    public function getSubNavigation(ActionRequest $request): array
    {
        $shopId = $this->shop->id;

        return [
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'label'    => __('Campaigns'),
                'number'   => WhatsappCampaign::where('shop_id', $shopId)->count(),
                'leftIcon' => [
                    'icon'    => 'fal fa-bullhorn',
                    'tooltip' => __('campaigns')
                ]
            ],
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.subscribers.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'label'    => __('Subscribers'),
                'number'   => WhatsappSubscriber::where('shop_id', $shopId)->count(),
                'leftIcon' => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('subscribers')
                ]
            ],
        ];
    }
}
