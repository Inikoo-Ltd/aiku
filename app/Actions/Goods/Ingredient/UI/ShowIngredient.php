<?php

/*
 * author Arya Permana - Kirin
 * created on 04-12-2024-14h-46m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Goods\Ingredient\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\SupplyChain\IngredientTabsEnum;
use App\Models\Goods\Ingredient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIngredient extends GrpAction
{
    use WithGoodsAuthorisation;

    public function handle(Ingredient $ingredient): Ingredient
    {

        return $ingredient;
    }


    public function asController(Ingredient $ingredient, ActionRequest $request): Ingredient
    {
        $group = group();
        $this->initialisation($group, $request)->withTab(IngredientTabsEnum::values());
        return $this->handle($ingredient);
    }

    public function htmlResponse(Ingredient $ingredient, ActionRequest $request): Response
    {

        return Inertia::render(
            'Goods/Ingredient',
            [
                 'title'       => __('ingredient'),
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $ingredient,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                 'navigation'  => [
                     'previous' => $this->getPrevious($ingredient, $request),
                     'next'     => $this->getNext($ingredient, $request),
                 ],
                 'pageHead'    => [
                     'icon'    => [
                         'title' => __('skus'),
                         'icon'  => 'fal fa-box'
                     ],
                     'title'   => $ingredient->name,
                    //  'actions' => [
                    //      $this->canEdit ? [
                    //          'type'  => 'button',
                    //          'style' => 'edit',
                    //          'route' => [
                    //              'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    //              'parameters' => array_values($request->route()->originalParameters())
                    //          ]
                    //      ] : false,
                    //      $this->canDelete ? [
                    //          'type'  => 'button',
                    //          'style' => 'delete',
                    //          'route' => [
                    //              'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.remove',
                    //              'parameters' => array_values($request->route()->originalParameters())
                    //          ]

                    //      ] : false
                    //  ]
                 ],
                 'tabs' => [
                     'current'    => $this->tab,
                     'navigation' => IngredientTabsEnum::navigation()

                 ],
             ]
        );
    }

    public function getBreadcrumbs(Ingredient $ingredient, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Ingredient $ingredient, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Ingredients')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $ingredient->slug,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.goods.ingredients.show' =>
            array_merge(
                ShowGoodsDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $ingredient,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => []
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

    public function getPrevious(Ingredient $ingredient, ActionRequest $request): ?array
    {
        $previous = Ingredient::where('slug', '<', $ingredient->slug)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Ingredient $ingredient, ActionRequest $request): ?array
    {
        $next = Ingredient::where('slug', '>', $ingredient->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Ingredient $ingredient, string $routeName): ?array
    {
        if (!$ingredient) {
            return null;
        }


        return match ($routeName) {
            'grp.goods.ingredients.show' => [
                'label' => $ingredient->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'ingredient' => $ingredient->slug
                    ]
                ]
            ],
        };
    }
}
