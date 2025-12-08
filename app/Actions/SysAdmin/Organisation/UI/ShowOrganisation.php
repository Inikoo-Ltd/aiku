<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 09 Dec 2023 03:25:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\UI\Organisation\OrgTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationResource;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisation extends GrpAction
{
    private Organisation $organisation;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.edit');
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation(app('group'), $request)->withTab(OrgTabsEnum::values());

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'Organisations/Organisation',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title' => __('Organisation'),
                'pageHead' => [
                    'model' => __('Organisation'),
                    'title' => $organisation->name,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name' => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => OrgTabsEnum::navigation(),
                ],
                OrgTabsEnum::SHOWCASE->value => $this->tab == OrgTabsEnum::SHOWCASE->value ?
                fn () => OrganisationResource::make($organisation)
                : Inertia::lazy(fn () => OrganisationResource::make($organisation)),

                OrgTabsEnum::HISTORY->value => $this->tab == OrgTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($organisation))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($organisation))),

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: OrgTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {

        $headCrumb = function (Organisation $organisation, array $routeParameters, string $suffix) {
            return [
                [

                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Organisations'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $organisation->slug,
                        ],

                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        $organisation = Organisation::where('slug', $routeParameters['organisation'])->first();

        return match ($routeName) {
            'grp.organisations.show' => array_merge(
                IndexOrganisations::make()->getBreadcrumbs(),
                $headCrumb(
                    $organisation,
                    [
                        'index' => [
                            'name' => 'grp.organisations.index',
                            'parameters' => [],
                        ],
                        'model' => [
                            'name' => 'grp.organisations.show',
                            'parameters' => $organisation->slug,
                        ],
                    ],
                    $suffix
                ),
            ),

            default => []
        };

    }
}
