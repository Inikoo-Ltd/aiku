<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowAgent extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Organisation|Shop $scope): Shop|Organisation
    {
        return $scope;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation);
    }

    public function inShop(Shop $shop): Shop
    {
        return $this->handle($shop);
    }

    public function htmlResponse(Organisation|Shop $scope, ActionRequest $request)
    {
        $indexAgentAction = IndexAgent::make();

        $agents = $indexAgentAction->handle($this->organisation, 'agents');

        return Inertia::render(
            'Agent/Agents',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => 'CRM Agent',
                'organisationSlug' => $this->organisation?->slug,
                'pageHeading' => [
                    'title'  => __('CRM Agent'),
                    'icon'   => [
                        'title' => __('CRM Agent'),
                        'icon'  => ['fal', 'fa-headset'],
                    ],
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Create CRM Agent'),
                            'label'   => __('Create CRM Agent'),
                            'route'   => [
                                'name'       => 'grp.org.chat.agents.create',
                                'parameters' => [$this->organisation->slug],
                            ],
                        ],
                    ],
                ],
                'data'        => $agents,
            ],
        )->table(
            $indexAgentAction->tableStructure(
                prefix: 'agents'
            ),
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            default =>
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'icon'  => 'fal fa-headset',
                            'route' => [
                                'name'       => 'grp.org.chat.agents.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('CRM Agent')
                        ]
                    ]
                ]
            )
        };
    }

}
