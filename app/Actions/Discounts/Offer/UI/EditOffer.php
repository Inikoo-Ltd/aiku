<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * GitHub: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Discounts\OfferCampaign;

class EditOffer extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Offer $offer, ActionRequest $request): Response
    {
        $getCategoryId = $this->getCategoryId($offer->allowance_signature);

        $productCategory = null;
        if ($getCategoryId) {
            $productCategory = ProductCategory::find($getCategoryId);
        }

        $offerResource  = OfferResource::make($offer)->resolve();
        $percentage_off = $offerResource['data_allowance_signature']['percentage_off'] * 100;

        $warning = null;
        if ($productCategory) {
            $warning = [
                'type'  => 'info',
                'title' => __('Info!'),
                'text'  => __('This offer apply on product category :prodCat', ['prodCat' => $productCategory->name]),
                'icon'  => ['fas', 'fa-exclamation-triangle']
            ];
        }

        // Section: Trigger
        $triggerValue = null;
        if ($offer->type == 'Category Quantity Ordered') {
            $triggerValue['trigger_item_quantity'] = $offer->trigger_data['item_quantity'] ?? 0;
        }
        if ($offer->type == 'Amount AND Order Number') {
            $triggerValue['trigger_order_number'] = $offer->trigger_data['order_number'] ?? 0;
            $triggerValue['trigger_min_amount']   = $offer->trigger_data['min_amount'] ?? 0;
        }

        // Section: Discount
        $discountValue = null;
        if (in_array($offer->type, ['Category Ordered', 'Category Quantity Ordered'])) {
            $discountValue['percentage_off'] = $percentage_off;
        }

        $blueprint = [];

        if ($offer->state != OfferStateEnum::FINISHED) {
            $blueprint = [
                [
                    'title'  => __('Properties'),
                    'fields' => [
                        'name'                => [
                            'type'        => 'input',
                            'label'       => __('Name'),
                            'placeholder' => __('Name'),
                            'required'    => true,
                            'value'       => $offer->name,
                        ],
                        'label'               => $offer->offerCampaign->type !== OfferCampaignTypeEnum::VOUCHERS ? [
                            'type'        => 'input',
                            'information' => __('Label to put on the discount coupon, if empty will take offer name'),
                            'label'       => __('Label'),
                            'placeholder' => __('Label'),
                            'required'    => true,
                            'value'       => $offer->label,
                        ] : null,
                        'date'                => app()->environment('local') ? [
                            'type'        => 'date',
                            'information' => __('The date until which the offer is valid. After this date, the offer will no longer be applicable.'),
                            'label'       => __('End Date'),
                            'placeholder' => __('date'),
                            'required'    => true,
                            'value'       => $offer->end_at,
                        ] : null,
                        'edit_offer_trigger'  => $triggerValue ? [
                            'type'          => 'editOffer',
                            'label'         => __('Trigger'),
                            'required'      => true,
                            'currency_code' => $this->organisation->currency->code,
                            'offer'         => $offer,
                            'value'         => $triggerValue,
                        ] : null,
                        'edit_offer_discount' => $discountValue ? [
                            'type'          => 'editOffer',
                            'label'         => __('Discount'),
                            'required'      => true,
                            'currency_code' => $this->organisation->currency->code,
                            'offer'         => $offer,
                            'value'         => $discountValue,
                        ] : null,
                    ]
                ],
            ];
        }


        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit Offer').' '.$offer->code,
                'pageHead'    => [
                    'title'     => $offer->name,
                    'model'     => __('Edit Offer'),
                    'icon'      => 'fal fa-pencil',
                    'iconRight' => $offer->state->stateIcon()[$offer->state->value],
                    'actions'   => array_filter([
                        $offer->state == OfferStateEnum::ACTIVE ? [
                            'type'  => 'button',
                            'label' => __('Finish Now'),
                            'style' => 'red',
                            'icon'  => 'fal fa-skull',
                            'route' => [
                                'name'       => 'grp.models.offer.finish',
                                'parameters' => $offer->id,
                            ],
                        ] : null,
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ]),
                ],
                'warning'     => $warning,
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  => $blueprint,
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.org.shops.show.discounts.offers.update',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ]
                ],

            ]
        );
    }

    public function getCategoryId(string $str): string|null
    {
        if (preg_match('/^all_products_in_product_category(?::(\d+))?:/', $str, $m)) {
            return $m[1] ?? null;
        }
        if (preg_match('/^all_products_in_department(?::(\d+))?:/', $str, $m)) {
            return $m[1] ?? null;
        }

        return null;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Exception
     */
    public function inOfferCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Exception
     */
    public function inGiftCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Exception
     */
    public function inAmnestyCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $request);
    }

    public function getBreadcrumbs(Offer $offer, string $routeName, array $routeParameters): array
    {
        return ShowOffer::make()->getBreadCrumbs(
            offer: $offer,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
