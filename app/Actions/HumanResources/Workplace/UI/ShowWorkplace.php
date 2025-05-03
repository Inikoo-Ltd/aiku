<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Workplace\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\WithWorkplaceSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\WorkplaceTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\WorkplaceResource;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWorkplace extends OrgAction
{
    use WithActionButtons;
    use WithWorkplaceSubNavigation;
    use WithHumanResourcesAuthorisation;


    public function handle(Workplace $workplace): Workplace
    {
        return $workplace;
    }


    public function asController(Organisation $organisation, Workplace $workplace, ActionRequest $request): Workplace
    {
        $this->initialisation($organisation, $request)->withTab(WorkplaceTabsEnum::values());

        return $this->handle($workplace);
    }

    public function htmlResponse(Workplace $workplace, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Workplace',
            [
                'title'                            => __('working place'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($workplace, $request),
                    'next'     => $this->getNext($workplace, $request),
                ],
                'pageHead'                         => [
                    'icon'        =>
                        [
                            'icon'  => ['fal', 'building'],
                            'title' => __('working place')
                        ],
                    'title'       => $workplace->name,
                    'model'       => __('workplace'),
                    'iconActions' => [
                        $this->canDelete ? $this->getDeleteActionIcon($request) : null,
                        $this->canEdit ? $this->getEditActionIcon($request) : null,
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],

                    'subNavigation' => $this->getWorkplaceSubNavigation($workplace)


                ],
                'tabs'                             => [

                    'current'    => $this->tab,
                    'navigation' => WorkplaceTabsEnum::navigation(),

                ],
                WorkplaceTabsEnum::SHOWCASE->value => $this->tab == WorkplaceTabsEnum::SHOWCASE->value ?
                    fn () => GetWorkplaceShowcase::run($workplace)
                    : Inertia::lazy(fn () => GetWorkplaceShowcase::run($workplace)),


                WorkplaceTabsEnum::HISTORY->value => $this->tab == WorkplaceTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($workplace))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($workplace)))
            ]
        )->table(IndexHistory::make()->tableStructure(WorkplaceTabsEnum::HISTORY->value));
    }

    public function jsonResponse(Workplace $workplace): WorkplaceResource
    {
        return WorkplaceResource::make($workplace);
    }

    public function getBreadcrumbs($routeParameters, $suffix = null): array
    {
        $workplace = Workplace::where('slug', $routeParameters['workplace'])->first();

        return array_merge(
            (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.hr.workplaces.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Working places'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.hr.workplaces.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $workplace->slug,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Workplace $workplace, ActionRequest $request): ?array
    {
        $previous = Workplace::where('slug', '<', $workplace->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Workplace $workplace, ActionRequest $request): ?array
    {
        $next = Workplace::where('slug', '>', $workplace->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Workplace $workplace, string $routeName): ?array
    {
        if (!$workplace) {
            return null;
        }

        return match ($routeName) {
            'grp.org.hr.workplaces.show' => [
                'label' => $workplace->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'workplace'    => $workplace->slug
                    ]
                ]
            ]
        };
    }




}
