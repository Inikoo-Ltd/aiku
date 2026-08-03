<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\Analytics\TrackWebsiteVisitorActivity;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowLiveUsers extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    public function handle(Website $website): array
    {
        $visitors = TrackWebsiteVisitorActivity::make()->getActiveVisitors($website);

        if (empty($visitors)) {
            return [];
        }

        $sessionIds = array_column($visitors, 'session_id');

        $visitorIdsMap = WebsiteVisitor::whereIn('session_id', $sessionIds)
            ->where('website_id', $website->id)
            ->pluck('id', 'session_id');

        $chatSessions = ChatSession::query()
            ->whereIn('website_visitor_id', $visitorIdsMap->values())
            ->with(['assignments' => function ($q) {
                $q->where('status', ChatAssignmentStatusEnum::ACTIVE);
            }, 'assignments.chatAgent.user'])
            ->get()
            ->keyBy('website_visitor_id');

        return array_map(function ($visitor) use ($visitorIdsMap, $chatSessions) {
            $sessionId = $visitor['session_id'];
            $visitorId = $visitorIdsMap[$sessionId] ?? null;

            if ($visitorId && isset($chatSessions[$visitorId])) {
                $chatSession = $chatSessions[$visitorId];
                $assignment  = $chatSession->assignments->first();

                $visitor['status'] = strtolower($chatSession->status->value);
                if ($assignment && $assignment->chatAgent) {
                    $visitor['agent']      = $assignment->chatAgent->user->contact_name ?? $assignment->chatAgent->user->name;
                    $visitor['department'] = $assignment->chatAgent->specialization[0] ?? 'General';
                    $visitor['status']     = 'assigned';
                }
            }

            return $visitor;
        }, $visitors);
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }

    public function htmlResponse(array $visitors, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->route()->parameter('website');
        $title = __('Live Visitors');

        return Inertia::render(
            'Org/Web/ShowLiveUsers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-users'],
                        'title' => __('Visitors')
                    ],
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($website),
                ],
                'website'     => $website,
                'visitors'    => $visitors,
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        $baseRoute = str_contains($routeName, '.shops.')
            ? 'grp.org.shops.show.web.analytics.dashboard'
            : 'grp.org.fulfilments.show.web.analytics.dashboard';

        return array_merge(
            ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs($baseRoute, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Live Visitors'),
                    ]
                ]
            ]
        );
    }
}
