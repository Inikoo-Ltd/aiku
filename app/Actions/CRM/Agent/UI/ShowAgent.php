<?php

namespace App\Actions\CRM\Agent\UI;

use Inertia\Inertia;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;

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
        return Inertia::render(
            'Agent/Agents',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => 'CRM Agent',
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
                                'name'       => 'grp.org.agents.create',
                                'organisation' => $this->organisation->slug,
                            ],
                        ],
                    ],
                ],
                'data'        => ChatAgentResource::collection(IndexAgent::run($this->organisation, __('Agents'))),
            ],
        )->table(
            IndexAgent::make()->tableStructure(
                prefix: 'Agents'
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
                            'route' => [
                                'name'       => 'grp.org.agents.show',
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
