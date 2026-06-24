<?php

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\CRM\ChatSession\GetChatDashboardData;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowChatDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $title         = __('Chat Dashboard');
        $dashboardData = GetChatDashboardData::run($organisation);

        return Inertia::render(
            'Org/Chat/Dashboard',
            [
                'breadcrumbs'      => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'            => $title,
                'pageHead'         => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'comment-alt'],
                        'title' => $title,
                    ],
                ],
                'stats'                 => $dashboardData['stats'],
                'chatEnabledShops'      => $dashboardData['chatEnabledShops'],
                'table'                 => $dashboardData['table'],
                'visitorsByCountryRoute' => route('grp.org.chat.visitors-by-country', $organisation->slug),
                'activeSessionsRoute'      => route('grp.org.chat.active-sessions', $organisation->slug),
                'dashboardVisitorsRoute'   => route('grp.org.chat.dashboard-visitors', $organisation->slug),
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            default => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'icon'  => 'fal fa-comment-alt',
                            'route' => [
                                'name'       => 'grp.org.chat.dashboard',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Chat Dashboard'),
                        ],
                    ],
                ]
            ),
        };
    }
}
