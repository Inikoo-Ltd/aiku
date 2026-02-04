<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class AddFreeGift extends OrgAction
{
    public function handle(): array
    {
        return [];
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): array
    {
        $this->parent = $offerCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle();
    }

    public function htmlResponse(array $data): Response
    {
        return Inertia::render(
            'Org/Discounts/FreeGiftForm',
            [
                'title'       => __('Free Gift'),
                'breadcrumbs' => $this->getBreadcrumbs($this->parent, request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('Free Gift'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => __('Free Gift')
                    ]
                ],
                'data'        => $data,
            ]
        );
    }

    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowOfferCampaign::make()->getBreadcrumbs($offerCampaign, $routeName, $routeParameters, $suffix),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-gift',
                        'label' => __('Free Gift'),
                        'route' => [
                            'name'       => 'grp.org.shops.discounts.campaigns.free_gift',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
