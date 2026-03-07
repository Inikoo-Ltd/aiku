<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Mar 2026 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditVolGrGift extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign): Response
    {
        $giftOffer     = null;
        $giftAllowance = null;
        $giftOfferId   = Arr::get($offerCampaign->data, 'vol_gr_gift_offer_id');
        if ($giftOfferId) {
            $giftOffer = Offer::find($giftOfferId);
            /** @var OfferAllowance $giftAllowance */
            $giftAllowance = $giftOffer->offerAllowances()->first();
        }


        if (!$giftOffer) {
            abort(404);
        }
        if (!$giftAllowance) {
            abort(423);
        }


        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('Edit Vol/GR Gift'),
                'breadcrumbs' => $this->getBreadcrumbs($offerCampaign,request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('Edit Vol/GR Gift'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => __('Edit Vol/GR Gift')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/edit_vol_gr_gift$/', 'show', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'title'  => __('Set up Vol/GR Gift'),
                            'fields' => [
                                'amount'   => [
                                    'type'        => 'input_number',
                                    'information' => __('The minimum order amount to be eligible for this Vol/GR Gift.'),
                                    'label'       => __('Min. amount'),
                                    'required'    => true,
                                    "bind"        => [
                                        'placeholder' => 0,
                                        'prefix'      => $offerCampaign->shop->currency->symbol,
                                        'min'         => 0
                                    ],
                                    'value'       => Arr::get($giftOffer->trigger_data, 'min_amount'),

                                ],
                                'products' => [
                                    'type'       => 'free_gift',
                                    'label'      => __('Products'),
                                    'required'   => true,
                                    'fetchRoute' => [
                                        'name'       => 'grp.json.shop.products_for_vol_gr_gift',
                                        'parameters' => [
                                            'shop' => $offerCampaign->shop->id,
                                        ],
                                    ],
                                    "value"      => Arr::get($giftAllowance->data, 'products'),
                                ],
                                'default'  => [
                                    'hidden' => true,
                                    'value'  => null
                                ]
                            ],
                        ],
                    ],
                    'route'      => [
                        'name'       => 'grp.models.offer.update_vol_gr_gift',
                        'parameters' => [
                            'offer' => $giftOffer->id,
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

    public function getBreadcrumbs(OfferCampaign $offerCampaign,string $routeName, array $routeParameters): array
    {

        return  ShowOfferCampaign::make()->getBreadcrumbs(
            $offerCampaign,
            routeName: preg_replace('/edit_vol_gr_gift$/', 'show', $routeName),
            routeParameters: $routeParameters,suffix: __('Edit Vol/GR Gift')
        );


    }
}
