<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Catalogue\ProductCategory\UI\ShowDepartment;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Catalogue\ProductCategory\UI\ShowSubDepartment;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\Discounts\OfferCampaign\UI\ShowOfferCampaign;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Http\Resources\Catalogue\OfferAllowanceResource;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOffer extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithSubDepartmentSubNavigation;
    use WithFamilySubNavigation;
    private ProductCategory|null $parent = null;

    public function handle(Offer $offer): Offer
    {
        return $offer;
    }

    public function htmlResponse(Offer $offer, ActionRequest $request): Response
    {
        $icon      = ['fal', 'fa-badge-percent'];
        $editRoute = null;
        $actions = [];
        $subNavigation = [];

        if ($offer->type == "VolGr Gift") {
            $editRoute = [
                'name'       => 'grp.org.shops.show.discounts.campaigns.offer.edit_vol_gr_gift',
                'parameters' => $request->route()->parameters()
            ];
        }

        if ($editRoute) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'route' => $editRoute
            ];
        }

        if ($this->parent?->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $subNavigation = $this->getDepartmentSubNavigation($this->parent);
        } elseif ($this->parent?->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $subNavigation = $this->getSubDepartmentSubNavigation($this->parent);
        } elseif ($this->parent?->type == ProductCategoryTypeEnum::FAMILY) {
            $subNavigation = $this->getFamilySubNavigation($this->parent, $offer->shop, $request);
        }


        preg_match('/^all_products_in_product_category(?::(\d+))?:/', $offer->allowance_signature, $m);
        $productCategory = isset($m[1]) ? ProductCategory::find($m[1]) : null;


        $vueComponent = match ($offer->type) {
            'VolGr Gift' => 'Org/Discounts/OfferVolGrGift',
            // 'Amount AND Order Number' => 'Org/Discounts/OfferAmountOrder',
            default => 'Org/Discounts/Offer'
        };


        $data['offer'] = OfferResource::make($offer);
        $data['offer_allowances'] = [];
        foreach ($offer->offerAllowances as $allowance) {
            $data['offer_allowances'][] = OfferAllowanceResource::make($allowance);
        }

        if ($offer->type == "VolGr Gift") {
            /** @var OfferAllowance $giftAllowance */
            $giftAllowance  = $offer->offerAllowances()->first();
            $productOptions = [];

            foreach (Arr::get($giftAllowance->data, 'products', []) as $productData) {
                $product = Product::find($productData['id']);
                if ($product) {
                    $productOptions[] = [
                        'id'      => $product->id,
                        'slug'    => $product->slug,
                        'code'    => $product->code,
                        'name'    => $product->name,
                        'web_images_main'       => data_get($product->web_images, 'main'),
                        'default' => Arr::get($productData, 'default', false),
                    ];
                }
            }
            $data['products'] = $productOptions;
        }


        return Inertia::render(
            $vueComponent,
            [
                'breadcrumbs'   => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'         => __('Offer').' '.$offer->code,
                'pageHead'      => [
                    'title'     => $offer->name,
                    'model'     => __('Offer'),
                    'iconRight' => OfferStateEnum::from($offer->state->value)->stateIcon()[$offer->state->value],
                    'icon'      => $icon,
                    'actions'   => $actions,
                    'subNavigation' => $subNavigation,
                ],
                'url_master'    => $productCategory && $offer->type === 'Category Quantity Ordered Order Interval' ? [
                    'name'       => 'grp.masters.master_shops.show.master_families.edit',
                    'parameters' => [
                        'masterShop'   => $offer->shop->masterShop->slug,
                        'masterFamily' => $productCategory->masterProductCategory->slug,
                        'section'      => '5'
                    ]
                ] : [],
                'data'          => $data,
                'currency_code' => $offer->shop->currency->code,
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOfferCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inGiftCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if ($offer->type != "VolGr Gift") {
            abort(404);
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAmnestyCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if ($offer->type != "GR Amnesty") {
            abort(404);
        }


        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, Offer $offer, ActionRequest $request): Offer
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Offer $offer, ActionRequest $request): Offer
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, Offer $offer, ActionRequest $request): Offer
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    public function getBreadcrumbs(Offer $offer, string $routeName, array $routeParameters, string|null $suffix = null): array
    {
        return match ($routeName) {
            'grp.org.shops.show.catalogue.departments.show.offers.show' =>
            array_merge(
                ShowDepartment::make()->getBreadcrumbs('grp.org.shops.show.catalogue.departments.show', $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.catalogue.sub_departments.show.offers.show' =>
            array_merge(
                ShowSubDepartment::make()->getBreadcrumbs($offer->trigger, 'grp.org.shops.show.catalogue.sub_departments.show', $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.catalogue.families.show.offers.show'    =>
            array_merge(
                ShowFamily::make()->getBreadcrumbs($offer->trigger, 'grp.org.shops.show.catalogue.families.show', $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.amnesty.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'gr_amnesty',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.gift.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'gr_gift',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.offer.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'offers',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.offers.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            )
        };
    }
}
