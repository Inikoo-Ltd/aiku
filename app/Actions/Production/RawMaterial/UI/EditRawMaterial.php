<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRawMaterial extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(RawMaterial $rawMaterial): RawMaterial
    {
        return $rawMaterial;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id,
                ]
            );
        }

        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return PalletResource::collection($storedItems);
    }

    public function htmlResponse(RawMaterial $rawMaterial, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title' => __('Edit raw material'),
                'pageHead' => [
                    'title' => __('Edit raw material'),
                    // 'actions'   => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'exitEdit',
                    //         'route' => [
                    //             'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                    //             'parameters' => array_values($request->route()->originalParameters())
                    //         ]
                    //     ]
                    // ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title' => __('Edit Raw Material'),
                            'label' => 'edit',
                            'icon' => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'type' => [
                                    'type' => 'select',
                                    'options' => RawMaterialTypeEnum::values(),
                                    'label' => __('type'),
                                    'value' => $rawMaterial->type,
                                    'required' => true,
                                ],
                                'state' => [
                                    'type' => 'select',
                                    'options' => RawMaterialStateEnum::values(),
                                    'label' => __('state'),
                                    'value' => $rawMaterial->state,
                                    'required' => true,
                                ],
                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'value' => $rawMaterial->code,
                                    'required' => true,
                                ],
                                'description' => [
                                    'type' => 'input',
                                    'label' => __('description'),
                                    'value' => $rawMaterial->description,
                                    'required' => true,
                                ],
                                'unit' => [
                                    'type' => 'select',
                                    'options' => RawMaterialUnitEnum::values(),
                                    'label' => __('unit'),
                                    'value' => $rawMaterial->unit,
                                    'required' => true,
                                ],
                                'unit_cost' => [
                                    'type' => 'input',
                                    'label' => __('unit cost'),
                                    'value' => $rawMaterial->unit_cost,
                                    'required' => true,
                                ],
                                'quantity_on_location' => [
                                    'type' => 'input',
                                    'label' => __('quantity on location'),
                                    'value' => $rawMaterial->quantity_on_location,
                                    'required' => true,
                                ],
                                'stock_status' => [
                                    'type' => 'select',
                                    'options' => RawMaterialStockStatusEnum::values(),
                                    'label' => __('stock status'),
                                    'value' => $rawMaterial->stock_status,
                                    'required' => true,
                                ],
                                // 'type' => [
                                //     'type'    => 'select',
                                //     'label'   => __('type'),
                                //     'value'   => $storedItem->type,
                                //     'required'=> true,
                                //     'options' => PalletTypeEnum::values()
                                // ],
                                // 'location' => [
                                //     'type'     => 'combobox',
                                //     'label'    => __('location'),
                                //     'value'    => '',
                                //     'required' => true,
                                //     'apiUrl'   => route('grp.json.locations') . '?filter[slug]=',
                                // ]
                            ],
                        ],
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.production.raw-materials.update',
                            'parameters' => [$this->parent->id, $rawMaterial->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function inOrganisation(Organisation $organisation, RawMaterial $rawMaterial, ActionRequest $request): RawMaterial
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($rawMaterial);
    }

    public function asController(Organisation $organisation, Production $production, RawMaterial $rawMaterial, ActionRequest $request): RawMaterial
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($rawMaterial);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowRawMaterial::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
                suffix: '('.__('Editing').')'
            ),
            [
                [
                    'type' => 'editingModel',
                    'editingModel' => [
                        'label' => __('Editing raw material'),
                    ],
                ],
            ]
        );
    }
}
