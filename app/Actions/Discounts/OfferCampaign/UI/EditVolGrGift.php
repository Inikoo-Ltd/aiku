<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Mar 2026 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
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
    public function handle(Offer $offer): Response
    {

        $offerCampaign = $offer->offerCampaign;

        $giftOffer = $offer;
        /** @var OfferAllowance $giftAllowance */
        $giftAllowance = $giftOffer->offerAllowances()->first();

        $productOptions = [];

        foreach (Arr::get($giftAllowance->data, 'products', []) as $productData) {
            $product = Product::find($productData['id']);
            if ($product) {
                $productOptions[] = [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'default' => Arr::get($productData, 'default', false),
                ];
            }
        }



        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Vol/GR Gift'),
                'breadcrumbs' => $this->getBreadcrumbs($offerCampaign, request()->route()->getName(), request()->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => __('Edit Vol/GR Gift'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => __('Edit Vol/GR Gift')
                    ],
                    'model'     => __('Offer'),
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
                                    "value"      => $productOptions,
                                ],
                            ],
                        ],
                    ],
                    'args'  => [
                        'updateRoute'      => [
                            'method'    => 'patch',
                            'name'       => 'grp.models.offer.update_vol_gr_gift',
                            'parameters' => [
                                'offer' => $giftOffer->id,
                            ],
                        ],
                    ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

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



        return $this->handle($giftOffer);
    }

    public function inOffer(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters): array
    {
        return ShowOfferCampaign::make()->getBreadcrumbs(
            $offerCampaign,
            routeName: preg_replace('/edit_vol_gr_gift$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: __('Edit Vol/GR Gift')
        );
    }
}
