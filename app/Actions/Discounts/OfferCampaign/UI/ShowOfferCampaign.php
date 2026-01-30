<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\UI\Discounts\OfferCampaignTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOfferCampaign extends OrgAction
{
    use WithOfferCampaignNavigation;
    use OfferCampaignVolumeDiscountTrait;
    use OfferCampaignFirstOrderTrait;
    use OfferCampaignCustomerOffersTrait;
    use OfferCampaignShopOffersTrait;
    use OfferCampaignCategoryOffersTrait;
    use OfferCampaignProductOffersTrait;
    use OfferCampaignDiscretionaryTrait;

    public function handle(OfferCampaign $offerCampaign): OfferCampaign
    {
        return $offerCampaign;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("discounts.{$this->shop->id}.edit");

        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): OfferCampaign
    {
        $this->initialisationFromShop($shop, $request)->withTab(OfferCampaignTabsEnum::values());

        return $this->handle($offerCampaign);
    }

    public function htmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return match ($offerCampaign->type) {
            OfferCampaignTypeEnum::VOLUME_DISCOUNT => $this->getVolumeDiscountHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::FIRST_ORDER     => $this->getFirstOrderHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::CUSTOMER_OFFERS => $this->getCustomerOffersHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::SHOP_OFFERS     => $this->getShopOffersHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::CATEGORY_OFFERS => $this->getCategoryOffersHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::PRODUCT_OFFERS  => $this->getProductOffersHtmlResponse($offerCampaign, $request),
            OfferCampaignTypeEnum::DISCRETIONARY   => $this->getDiscretionaryHtmlResponse($offerCampaign, $request),
        };
    }

    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OfferCampaign $offerCampaign, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Offer campaigns')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $offerCampaign->slug,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $offerCampaign,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
