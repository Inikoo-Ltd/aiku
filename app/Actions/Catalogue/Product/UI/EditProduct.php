<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditProduct extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|Shop|Fulfilment|ProductCategory $parent;

    public function handle(Product $product): Product
    {
        return $product;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): Product
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Product $product, ActionRequest $request): Product
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, Product $product, ActionRequest $request): Product
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, Product $product, ActionRequest $request): Product
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request)->withTab(ProductTabsEnum::values());

        return $this->handle($product);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('goods'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $product,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($product, $request),
                    'next'     => $this->getNext($product, $request),
                ],
                'pageHead'    => [
                    'title'   => $product->code,
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('goods')
                        ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint($product),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product.update',
                            'parameters' => [
                                'product' => $product->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function getBlueprint(Product $product): array
    {
        return [
            [
                'label'  => __('Properties'),
                'title'  => __('id'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'code'        => [
                        'type'     => 'input',
                        'label'    => __('code'),
                        'value'    => $product->code,
                        'readonly' => true
                    ],
                    'name'        => [
                        'type'  => 'input',
                        'label' => __('label'),
                        'value' => $product->name,
                    ],
                    'description' => [
                        'type'  => 'textEditor',
                        'label' => __('description'),
                        'value' => $product->description
                    ],
                    'unit'        => [
                        'type'  => 'input',
                        'label' => __('unit'),
                        'value' => $product->unit,
                    ],
                    'units'       => [
                        'type'  => 'input',
                        'label' => __('units'),
                        'value' => $product->units,
                    ],
                    'price'       => [
                        'type'     => 'input',
                        'label'    => __('price'),
                        'required' => true,
                        'value'    => $product->price
                    ],
                    'state'       => [
                        'type'     => 'select',
                        'label'    => __('state'),
                        'required' => true,
                        'value'    => $product->state,
                        'options'  => Options::forEnum(AssetStateEnum::class)
                    ],
                ]
            ],
            [
                'label'  => __('Family'),
                'icon'   => 'fa-light fa-folder',
                'fields' => [
                    'family_id' => [
                        'type'       => 'select_infinite',
                        'label'      => __('Family'),
                        'options'    => [
                            [
                                'id'   => $product->family?->id,
                                'code' => $product->family?->code
                            ]
                        ],
                        'fetchRoute' => [
                            'name'       => 'grp.org.shops.show.catalogue.families.index',
                            'parameters' => [
                                'organisation' => $product->organisation->slug,
                                'shop'         => $product->shop->slug
                            ]
                        ],
                        'valueProp'  => 'id',
                        'labelProp'  => 'code',
                        'required'   => true,
                        'value'      => $product->family->id ?? null,
                    ]
                ],
            ],

        ];
    }

    public function getBreadcrumbs(Product $product, string $routeName, array $routeParameters): array
    {
        return ShowProduct::make()->getBreadcrumbs(
            parent: $this->parent,
            product: $product,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Product $product, ActionRequest $request): ?array
    {
        $previous = Product::where('slug', '<', $product->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Product $product, ActionRequest $request): ?array
    {
        $next = Product::where('slug', '>', $product->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Product $product, string $routeName): ?array
    {
        if (!$product) {
            return null;
        }

        return match ($routeName) {
            'shops.products.edit' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'product' => $product->slug
                    ]

                ]
            ],
            'shops.show.products.edit' => [
                'label' => $product->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'    => $product->shop->slug,
                        'product' => $product->slug
                    ]

                ]
            ],
            default => null,
        };
    }
}
