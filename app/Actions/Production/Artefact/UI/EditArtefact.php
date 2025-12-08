<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditArtefact extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(Artefact $artefact): Artefact
    {
        return $artefact;
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

    public function htmlResponse(Artefact $artefact, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title' => __('Edit manufacture task'),
                'pageHead' => [
                    'title' => __('Edit manufacture task'),
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
                            'title' => __('Edit Manufacture Task'),
                            'label' => 'edit',
                            'icon' => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'value' => $artefact->code,
                                    'required' => true,
                                ],
                                'name' => [
                                    'type' => 'input',
                                    'label' => __('name'),
                                    'value' => $artefact->name,
                                    'required' => true,
                                ],
                                // 'state' => [
                                //     'type'     => 'select',
                                //     'options'  => ArtefactStateEnum::values(),
                                //     'label'    => __('state'),
                                //     'value'    => $artefact->state,
                                //     'required' => false
                                // ],
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
                            'name' => 'grp.models.production.artefacts.update',
                            'parameters' => [$this->parent->id, $artefact->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function inOrganisation(Organisation $organisation, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($artefact);
    }

    public function asController(Organisation $organisation, Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowArtefact::make()->getBreadcrumbs(
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
