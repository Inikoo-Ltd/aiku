<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowChatDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithChatReportsResponse;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->chatAgent
            || $request->user()->authTo(['accounting.'.$this->organisation->id.'.view', 'org-supervisor.'.$this->organisation->id, 'shops-view.'.$this->organisation->id]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $shops = Shop::query()
            ->where('organisation_id', $organisation->id)
            ->where('state', ShopStateEnum::OPEN)
            ->get(['id', 'slug', 'name']);

        return Inertia::render(
            'Chat/ChatReports',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                ...$this->chatReportsProps($shops),
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
                                'name'       => 'grp.org.chat.reports',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Chat Reports'),
                        ],
                    ],
                ]
            ),
        };
    }
}
