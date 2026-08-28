<?php

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\OrgAction;
use App\Actions\Traits\WithWhatsappCampaignsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWhatsappSubscribers extends OrgAction
{
    use WithWhatsappCampaignsSubNavigation;

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
        $title = __('Subscribers');

        return Inertia::render(
            'Org/Marketing/WhatsappSubscribers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-users'],
                        'title' => $title,
                    ],
                    'subNavigation' => $this->getSubNavigation($request),
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexWhatsappCampaigns::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.subscribers.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Subscribers'),
                        'icon'  => 'fal fa-users'
                    ],
                ],
            ],
        );
    }
}
