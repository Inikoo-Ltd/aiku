<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Ingredient\UI;

use App\Actions\OrgAction;
use App\Models\Goods\Ingredient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditIngredient extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['goods.edit', 'compliance.edit']);
    }

    public function asController(Ingredient $ingredient, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($ingredient, $request);
    }

    public function handle(Ingredient $ingredient, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => ShowIngredient::make()->getBreadcrumbs(
                    $ingredient,
                    'grp.goods.ingredients.show',
                    $request->route()->originalParameters(),
                    '('.__('Editing').')'
                ),
                'title'       => __('Edit ingredient').' '.$ingredient->name,
                'pageHead'    => [
                    'title'   => __('Edit ingredient'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.goods.ingredients.show',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Name'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'name' => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'information' => __('Shared by every product with this ingredient: their ingredients lists are updated too. If the right spelling already exists, use Merge below.'),
                                    'value'       => $ingredient->name
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Merge'),
                            'icon'   => 'fa-light fa-code-merge',
                            'fields' => [
                                'merge_into' => [
                                    'type'             => 'select_infinite',
                                    'label'            => __('Merge into'),
                                    'information'      => __('For a misspelt or duplicated ingredient: its products move to the one you choose, in the same place in their lists, and this one is deleted.'),
                                    'value'            => null,
                                    'options'          => [],
                                    'fetchRoute'       => [
                                        'name'       => 'grp.goods.ingredients.index',
                                        'parameters' => []
                                    ],
                                    'valueProp'        => 'slug',
                                    'labelProp'        => 'name',
                                    'updateRoute'      => [
                                        'name'       => 'grp.models.ingredient.merge',
                                        'parameters' => [
                                            'ingredient' => $ingredient->id
                                        ]
                                    ],
                                    'saveConfirmation' => [
                                        'title'       => __('Merge :name?', ['name' => $ingredient->name]),
                                        'description' => __('Its products will use the ingredient you chose instead, and :name will be deleted. This cannot be undone.', ['name' => $ingredient->name]),
                                        'yesLabel'    => __('Merge'),
                                    ],
                                ],
                            ]
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.ingredient.update',
                            'parameters' => [
                                'ingredient' => $ingredient->id
                            ]
                        ]
                    ],
                ]
            ]
        );
    }
}
