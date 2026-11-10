<?php

namespace App\Actions\Traits;

use App\Models\Chat\MetaMessageTemplate;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappSubscriber;
use Lorisleiva\Actions\ActionRequest;

trait WithWhatsappCampaignsSubNavigation
{
    /**
     * The Templates tab opens the shop's WhatsApp templates page, which has its own, chat side,
     * permissions, so each half of the bar shows only to those who can open it.
     *
     * The counts are live rather than hydrated: a shop holds a few hundred subscribers and a
     * handful of campaigns, so counting on each tab is cheaper than keeping a stats column in step.
     * Soft deleted rows are left out by the models own scopes.
     */
    public function getSubNavigation(ActionRequest $request): array
    {
        $shopId = $this->shop->id;
        $user   = $request->user();

        $campaignTabs = $user->authTo("marketing.$shopId.view") ? [
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
        ] : [];

        $canSeeTemplates = $user->authTo([
            "chat-m.$shopId",
            "crm.$shopId.view",
            "crm.$shopId.edit",
            "org-admin.{$this->organisation->id}",
            "accounting.{$this->organisation->id}.view",
        ]);

        return array_merge($campaignTabs, $canSeeTemplates ? [
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $this->shop->slug,
                    ],
                ],
                'label'    => __('Templates'),
                'number'   => MetaMessageTemplate::where('shop_id', $shopId)
                    ->whereHas('metaChannel', fn ($query) => $query->where('code', 'whatsapp'))
                    ->count(),
                'leftIcon' => [
                    'icon'    => 'fal fa-layer-group',
                    'tooltip' => __('templates')
                ]
            ],
        ] : []);
    }
}
