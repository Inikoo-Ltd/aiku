<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\Artisan\GetArtisanAssignmentProps;
use App\Enums\UI\Production\ArtefactDepartmentTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ArtefactDepartmentsResource;
use App\Http\Resources\Production\ArtefactsResource;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowArtefactDepartment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function asController(Organisation $organisation, Production $production, ArtefactDepartment $artefactDepartment, ActionRequest $request): ArtefactDepartment
    {
        $this->initialisationFromProduction($production, $request)->withTab(ArtefactDepartmentTabsEnum::values());

        return $artefactDepartment;
    }

    public function htmlResponse(ArtefactDepartment $artefactDepartment, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ArtefactDepartment',
            [
                'title'       => $artefactDepartment->name,
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'    => ['icon' => ['fal', 'fa-folder'], 'title' => __('Artefact department')],
                    'model'   => __('Artefact department'),
                    'title'   => $artefactDepartment->name,
                    'afterTitle' => ['label' => $artefactDepartment->code],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_departments.edit',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : null
                    ],
                ],
                'move_to_department' => IndexArtefacts::make()->getMoveToDepartmentProps($this->production, $this->canEdit),
                'artisans'       => GetArtisanAssignmentProps::run($artefactDepartment, $this->canEdit),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ArtefactDepartmentTabsEnum::navigation()
                ],
                ArtefactDepartmentTabsEnum::ARTEFACTS->value => $this->tab == ArtefactDepartmentTabsEnum::ARTEFACTS->value ?
                    fn () => ArtefactsResource::collection(IndexArtefacts::run($artefactDepartment, ArtefactDepartmentTabsEnum::ARTEFACTS->value))
                    : Inertia::optional(fn () => ArtefactsResource::collection(IndexArtefacts::run($artefactDepartment, ArtefactDepartmentTabsEnum::ARTEFACTS->value))),
                ArtefactDepartmentTabsEnum::HISTORY->value => $this->tab == ArtefactDepartmentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($artefactDepartment, ArtefactDepartmentTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($artefactDepartment, ArtefactDepartmentTabsEnum::HISTORY->value))),
            ]
        )->table(IndexArtefacts::make()->tableStructure(parent: $artefactDepartment, prefix: ArtefactDepartmentTabsEnum::ARTEFACTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: ArtefactDepartmentTabsEnum::HISTORY->value));
    }

    public function jsonResponse(ArtefactDepartment $artefactDepartment): ArtefactDepartmentsResource
    {
        return new ArtefactDepartmentsResource($artefactDepartment);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $artefactDepartment = ArtefactDepartment::where('slug', $routeParameters['artefactDepartment'])->first();

        return array_merge(
            IndexArtefactDepartments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefact_departments.show',
                            'parameters' => $routeParameters
                        ],
                        'label' => $artefactDepartment?->code,
                        'icon'  => 'fal fa-folder',
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }
}
