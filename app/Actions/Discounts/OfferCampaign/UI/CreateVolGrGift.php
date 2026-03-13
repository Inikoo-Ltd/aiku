<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateVolGrGift extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('Vol/GR Gift'),
                'breadcrumbs' => $this->getBreadcrumbs($offerCampaign, request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('Vol/GR Gift'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => __('Vol/GR Gift')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create_vol_gr_gift$/', 'show', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'  => __('Set up Vol/GR Gift'),
                            'fields' => [
                                'amount' => [
                                    'type'     => 'input_number',
                                    'information'   => __('The minimum order amount to be eligible for this Vol/GR Gift.'),
                                    'label'    => __('Min. amount'),
                                    'required' => true,
                                    "bind"     => [
                                        'placeholder'   => 0,
                                        'prefix'   => $offerCampaign->shop->currency->symbol,
                                        'min'      => 0
                                    ],
                                    'value' => 0,

                                ],
                                'products' => [
                                    'type'       => 'free_gift',
                                    'label'      => __('Products'),
                                    'required'   => true,
                                    'fetchRoute' => [
                                        'name'       => 'grp.json.shop.products_for_vol_gr_gift',
                                        'parameters' => [
                                            'shop'         => $offerCampaign->shop->id,
                                        ],
                                    ],
                                    "value" => [],
                                ],
                                // 'default'   => [
                                //     'hidden' => true,
                                //     'value' => null
                                // ]
                            ],
                        ],
                    ],
                    'route' => [
                        'name'       => 'grp.models.offer_campaign.store_vol_gr_gift',
                        'parameters' => [
                            'offerCampaign'  => $offerCampaign->id,
                        ],
                    ],
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offerCampaign);
    }

    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters): array
    {

        return array_merge(
            ShowOfferCampaign::make()->getBreadcrumbs(
                $offerCampaign,
                routeName: preg_replace('/create_vol_gr_gift$/', 'show', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Setting up Vol/GR Gift'),
                    ]
                ]
            ]
        );


    }
}
