<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateArtefact extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(ActionRequest $request): Response
    {
        $orgStock  = $request->integer('org_stock') ? OrgStock::where('organisation_id', $this->organisation->id)->find($request->integer('org_stock')) : null;
        $tradeUnit = $orgStock?->tradeUnits()->when($request->integer('trade_unit'), fn ($query, $tradeUnitId) => $query->where('trade_units.id', $tradeUnitId))->first();
        $family    = $orgStock?->org_stock_family_id && $this->parent instanceof Production
            ? ArtefactFamily::where('production_id', $this->parent->id)->where('org_stock_family_id', $orgStock->org_stock_family_id)->first()
            : null;
        $familyArtefacts = $family ? Artefact::where('artefact_family_id', $family->id) : null;
        $usualBatchSize  = $familyArtefacts?->clone()->whereNotNull('recommended_batch_size')->groupBy('recommended_batch_size')->orderByRaw('count(*) desc')->value('recommended_batch_size');
        $usualShelfLife  = $familyArtefacts?->clone()->whereNotNull('shelf_life_days')->groupBy('shelf_life_days')->orderByRaw('count(*) desc')->value('shelf_life_days');

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New artefact'),
                'pageHead'    => [
                    'title'        => __('New artefact'),
                    'icon'         => [
                        'title' => __('Create artefact'),
                        'icon'  => 'fal fa-hamsa'
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create artefact'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'placeholder' => __('Enter new code') . ' (e.g. NewArtefact-001)',
                                    'value'    => $orgStock?->code ?? '',
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'placeholder' => __('Enter new name') . ' (e.g. New Artefact)',
                                    'value'    => $orgStock?->name ?? '',
                                    'required' => true
                                ],
                                'recommended_batch_size' => [
                                    'type'     => 'input_number',
                                    'label'    => __('Recommended batch size'),
                                    'bind'  => [
                                        'min' => 0,
                                        'placeholder' => __('Enter recommended batch size') . ' (e.g. 100)',
                                    ],
                                    'value'    => $usualBatchSize ?? '',
                                    'required' => false
                                ],
                                'shelf_life_days' => [
                                    'type'     => 'input_number',
                                    'label'    => __('Shelf life (days)'),
                                    'bind'  => [
                                        'min' => 0,
                                        'placeholder' => __('How long it keeps') . ' (365 = 1 year)',
                                    ],
                                    'value'    => $usualShelfLife ?? '',
                                    'required' => false
                                ],
                                ...($this->parent instanceof Production ? [
                                    'artefact_family_id' => [
                                        'type'       => 'select_infinite',
                                        'label'      => __('Family'),
                                        'options'    => array_filter([$family ? ['id' => $family->id, 'name' => $family->name] : null]),
                                        'fetchRoute' => [
                                            'name'       => 'grp.json.production.artefact_families.index',
                                            'parameters' => ['production' => $this->parent->id]
                                        ],
                                        'valueProp' => 'id',
                                        'labelProp' => 'name',
                                        'required'  => false,
                                        'value'     => $family?->id,
                                    ],
                                ] : []),
                                'trade_unit_id' => [
                                    'type'       => 'select_infinite',
                                    'label'      => __('Trade unit'),
                                    'placeholder' => __('Select a trade unit'),
                                    'options'    => array_filter([$tradeUnit ? ['id' => $tradeUnit->id, 'code' => $tradeUnit->code] : null]),
                                    'fetchRoute' => [
                                        'name'       => 'grp.goods.trade-units.index',
                                        'parameters' => []
                                    ],
                                    'valueProp' => 'id',
                                    'labelProp' => 'code',
                                    'required'  => false,
                                    'value'     => $tradeUnit?->id,
                                ],
                                'org_stock_id' => [
                                    'type'       => 'select_infinite',
                                    'label'      => __('Stock (SKU)'),
                                    'placeholder' => __('Select a stock'),
                                    'options'    => array_filter([$orgStock ? ['id' => $orgStock->id, 'code' => $orgStock->code] : null]),
                                    'fetchRoute' => [
                                        'name'       => 'grp.json.org_stocks.index',
                                        'parameters' => [
                                            'organisation' => $this->organisation->id,
                                        ]
                                    ],
                                    'valueProp' => 'id',
                                    'labelProp' => 'code',
                                    'required'  => false,
                                    'value'     => $orgStock?->id,
                                ],
                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.production.artefacts.store',
                        'parameters' => [$this->parent->id]
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id
                ]
            );
        }

        $this->canEdit = $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Response
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexArtefacts::make()->getBreadcrumbs(request()->route()->getName(), $routeParameters),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Artefact'),
                    ]
                ]
            ]
        );
    }
}
