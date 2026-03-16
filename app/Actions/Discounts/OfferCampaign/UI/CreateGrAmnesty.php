<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026 09:50:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateGrAmnesty extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('GR Amnesty'),
                'breadcrumbs' => $this->getBreadcrumbs($offerCampaign, request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('GR Amnesty'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-candle-holder'],
                        'title'   => __('GR Amnesty'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create_gr_amnesty_offer$/', 'show', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'   => __('Set up GR Amnesty'),
                            'fields' => [
                                'start_at' => [
                                    'type' => 'date',
                                    'label' => __('From'),
                                    'value' => date('Y-m-d'),
                                ],
                                'end_at'   => [
                                    'type' => 'date',
                                    'label' => __('Until'),
                                    'value' => Carbon::today()->addWeek()->format('Y-m-d'),
                                ]


                            ],
                        ],
                    ],
                    'route'      => [
                        'name'       => 'grp.models.offer_campaign.store_gr_amnesty',
                        'parameters' => [
                            'offerCampaign' => $offerCampaign->id,
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
                routeName: preg_replace('/create_gr_amnesty_offer$/', 'show', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Setting up GR Amnesty'),
                    ]
                ]
            ]
        );
    }
}
