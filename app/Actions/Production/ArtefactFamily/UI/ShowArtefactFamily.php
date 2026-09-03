<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Enums\UI\Production\ArtefactFamilyTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ArtefactFamiliesResource;
use App\Http\Resources\Production\ArtefactsResource;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowArtefactFamily extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function asController(Organisation $organisation, Production $production, ArtefactFamily $artefactFamily, ActionRequest $request): ArtefactFamily
    {
        $this->initialisationFromProduction($production, $request)->withTab(ArtefactFamilyTabsEnum::values());

        return $artefactFamily;
    }

    public function htmlResponse(ArtefactFamily $artefactFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ArtefactFamily',
            [
                'title'       => $artefactFamily->name,
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'    => ['icon' => ['fal', 'fa-folder'], 'title' => __('Artefact family')],
                    'model'   => __('Artefact family'),
                    'title'   => $artefactFamily->name,
                    'afterTitle' => ['label' => $artefactFamily->code],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_families.edit',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : null
                    ],
                ],
                'move_to_family' => IndexArtefacts::make()->getMoveToFamilyProps($this->production, $this->canEdit),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ArtefactFamilyTabsEnum::navigation()
                ],
                ArtefactFamilyTabsEnum::ARTEFACTS->value => $this->tab == ArtefactFamilyTabsEnum::ARTEFACTS->value ?
                    fn () => ArtefactsResource::collection(IndexArtefacts::run($artefactFamily, ArtefactFamilyTabsEnum::ARTEFACTS->value))
                    : Inertia::optional(fn () => ArtefactsResource::collection(IndexArtefacts::run($artefactFamily, ArtefactFamilyTabsEnum::ARTEFACTS->value))),
                ArtefactFamilyTabsEnum::HISTORY->value => $this->tab == ArtefactFamilyTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($artefactFamily, ArtefactFamilyTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($artefactFamily, ArtefactFamilyTabsEnum::HISTORY->value))),
            ]
        )->table(IndexArtefacts::make()->tableStructure(parent: $artefactFamily, prefix: ArtefactFamilyTabsEnum::ARTEFACTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: ArtefactFamilyTabsEnum::HISTORY->value));
    }

    public function jsonResponse(ArtefactFamily $artefactFamily): ArtefactFamiliesResource
    {
        return new ArtefactFamiliesResource($artefactFamily);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $artefactFamily = ArtefactFamily::where('slug', $routeParameters['artefactFamily'])->first();

        return array_merge(
            IndexArtefactFamilies::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefact_families.show',
                            'parameters' => $routeParameters
                        ],
                        'label' => $artefactFamily?->code,
                        'icon'  => 'fal fa-folder',
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }
}
