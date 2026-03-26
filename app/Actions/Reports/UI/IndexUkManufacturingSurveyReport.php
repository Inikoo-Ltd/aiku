<?php

namespace App\Actions\Reports\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexUkManufacturingSurveyReport extends OrgAction
{
    use AsAction;

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Reports/UkManufacturingSurveyReport',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'         => __('UK Manufacturing Survey'),
                'pageHead'      => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-industry'],
                        'title' => __('UK Manufacturing Survey'),
                    ],
                    'title' => __('UK Manufacturing Survey'),
                ],
                'downloadRoute' => [
                    'name'       => 'grp.org.reports.uk-manufacturing-survey.export',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-industry',
                        'label' => __('UK Manufacturing Survey'),
                        'route' => [
                            'name'       => 'grp.org.reports.uk-manufacturing-survey',
                            'parameters' => $routeParameters,
                        ],
                    ],
                ],
            ]
        );
    }
}
