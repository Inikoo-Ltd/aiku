<?php

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWhatsappCampaigns extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("marketing.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Whatsapp Campaigns');

        return Inertia::render(
            'Org/Marketing/WhatsappCampaigns',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => $title,
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Whatsapp Campaigns'),
                        'icon'  => 'fab fa-whatsapp'
                    ],
                ],
            ],
        );
    }
}
