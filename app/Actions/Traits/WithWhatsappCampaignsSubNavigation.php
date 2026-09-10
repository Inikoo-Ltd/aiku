<?php

namespace App\Actions\Traits;

use Lorisleiva\Actions\ActionRequest;

trait WithWhatsappCampaignsSubNavigation
{
    public function getSubNavigation(ActionRequest $request): array
    {
        return [
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'label'    => __('Campaigns'),
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
                'leftIcon' => [
                    'icon'    => 'fal fa-users',
                    'tooltip' => __('subscribers')
                ]
            ],
        ];
    }
}
