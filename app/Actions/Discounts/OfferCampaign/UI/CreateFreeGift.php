<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateFreeGift extends OrgAction
{
    public function handle(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('Free Gift'),
                'breadcrumbs' => $this->getBreadcrumbs(request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('Free Gift'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => __('Free Gift')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/free_gift$/', 'show', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'  => __('Create Free Gift'),
                            'fields' => [
                                'amount' => [
                                    'type'     => 'input_number',
                                    'information'   => __('The minimum order amount to be eligible for this free gift.'),
                                    'label'    => __('Min. amount'),
                                    'required' => true,
                                    "bind"     => [
                                        'placeholder'   => 0,
                                        'prefix'   => $shop->currency->symbol,
                                        'min'      => 0
                                    ],

                                ],
                                'products' => [
                                    'type'       => 'select_infinite',
                                    'label'      => __('Products'),
                                    'required'   => true,
                                    'mode'       => "tags",
                                    'valueProp'  => "id" ,
                                    'label-prop' => "name",
                                    'type_label' => 'product',
                                    'fetchRoute' => [
                                        'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                                        'parameters' => [
                                            'organisation' => $organisation->slug,
                                            'shop'         => $shop->slug,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'route' => [
                        'name'       => 'grp.org.shops.show.discounts.campaigns.store_free_gift',
                        'parameters' => [
                            'organisation'   => $organisation->slug,
                            'shop'           => $shop->slug,
                            'offerCampaign'  => $offerCampaign->slug,
                        ],
                    ],
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($organisation, $shop, $offerCampaign, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-gift',
                    'label' => __('Free Gift'),
                    'route' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ]
            ],
        ];
    }
}
