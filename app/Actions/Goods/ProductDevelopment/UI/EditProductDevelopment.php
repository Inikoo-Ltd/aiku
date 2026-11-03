<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-13h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\ChangeProductDevelopmentKind;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Production\Production;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditProductDevelopment extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && $this->canEditProductDevelopment($request->user(), $this->productDevelopment);
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup(group(), $request);

        return $productDevelopment;
    }

    public function htmlResponse(ProductDevelopment $productDevelopment): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit :code', ['code' => $productDevelopment->code]),
                'breadcrumbs' => ShowProductDevelopment::make()->getShowBreadcrumbs($productDevelopment),
                'pageHead'    => [
                    'title'   => __('Edit'),
                    'icon'    => 'fal fa-lightbulb-on',
                    'model'   => $productDevelopment->code,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.product_developments.show',
                                'parameters' => ['productDevelopment' => $productDevelopment->slug],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Development'),
                            'icon'   => 'fal fa-lightbulb-on',
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Title'),
                                    'required' => true,
                                    'value'    => $productDevelopment->name,
                                ],
                            ],
                        ],
                        [
                            'label'  => __('Development kind'),
                            'icon'   => 'fal fa-flask',
                            'fields' => [
                                'kind' => $this->kindField($productDevelopment),
                            ],
                        ],
                        [
                            'label'  => __('Cancel development'),
                            'icon'   => 'fal fa-ban',
                            'fields' => [
                                'cancel_development' => [
                                    'type'         => 'product_development_cancel',
                                    'label'        => __('Stop this development'),
                                    'noSaveButton' => true,
                                    'value'        => null,
                                    'route'        => [
                                        'name'       => 'grp.models.product_development.cancel',
                                        'parameters' => ['productDevelopment' => $productDevelopment->id],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_development.update',
                            'parameters' => ['productDevelopment' => $productDevelopment->id],
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function kindField(ProductDevelopment $productDevelopment): array
    {
        $descriptions = ProductDevelopmentKindEnum::descriptions();

        return [
            'type'         => 'product_development_kind',
            'label'        => __('What is being developed'),
            'noSaveButton' => true,
            'value'        => $productDevelopment->kind->value,
            'options'      => collect(ProductDevelopmentKindEnum::cases())->map(fn (ProductDevelopmentKindEnum $kind) => [
                'value'       => $kind->value,
                'label'       => ProductDevelopmentKindEnum::labels()[$kind->value],
                'description' => $descriptions[$kind->value],
                'icon'        => $kind->icon(),
            ])->all(),
            'blocked'      => ChangeProductDevelopmentKind::blockedReason($productDevelopment),
            'route'        => [
                'name'       => 'grp.models.product_development.kind',
                'parameters' => ['productDevelopment' => $productDevelopment->id],
            ],
            'changes'      => [
                ProductDevelopmentKindEnum::RECIPE->value => [
                    __('Commercial terms and the purchasing items are removed, and a Formulation & trial runs stage is added.'),
                    __('The agent\'s spec items go to the Production role, keeping the answers already given.'),
                    __('The supplier, agent and factory photos are cleared.'),
                    __('Each SKU gets its experiment in the production unit\'s Development room.'),
                ],
                ProductDevelopmentKindEnum::PRODUCT->value => [
                    __('Formulation & trial runs and the components are removed, and Commercial terms comes back with its purchasing items.'),
                    __('The production team\'s spec items go back to the Agent role, keeping the answers already given.'),
                    __('The experiments stay in the production unit\'s Development room, where Production can discard them.'),
                ],
            ],
            'productions'  => Production::where('group_id', $productDevelopment->group_id)->whereNot('state', ProductionStateEnum::CLOSED->value)->with('organisation')->orderBy('name')->get()
                ->map(fn (Production $production) => [
                    'value'        => $production->id,
                    'label'        => $production->name,
                    'organisation' => $production->organisation?->name,
                ])->all(),
        ];
    }
}
