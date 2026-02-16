<?php

namespace App\Actions\HumanResources\Overtime\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexOvertime extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(Organisation $organisation): array
    {
        return [];
    }

    public function htmlResponse(array $payload, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Overtime',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Overtime'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => __('Human resources')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-clock'],
                        'title' => __('Overtime')
                    ],
                    'title'         => __('Overtime'),
                    'subNavigation' => [
                        [
                            'label'    => __('Overview'),
                            'route'    => [
                                'name'       => 'grp.org.hr.overtime.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                            'leftIcon' => [
                                'icon'    => ['fal', 'fa-chart-line'],
                                'tooltip' => __('Overtime overview'),
                            ],
                        ],
                        [
                            'label'    => __('Overtime types'),
                            'route'    => [
                                'name'       => 'grp.org.hr.overtime_types.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                            'leftIcon' => [
                                'icon'    => ['fal', 'fa-layer-group'],
                                'tooltip' => __('Overtime types'),
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Overtime'),
                        'icon'  => 'fal fa-clock',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.overtime.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
