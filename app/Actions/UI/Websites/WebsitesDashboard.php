<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 23:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Websites;

use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\Web\Crawl\UI\IndexCrawls;
use App\Enums\Web\Website\WebsiteDashboardTabsEnum;
use App\Http\Resources\Web\CrawlsResource;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsitesDashboard extends GrpAction
{
    use AsAction;

    public function asController(ActionRequest $request): Group
    {
        $this->initialisation(group(), $request)->withTab(WebsiteDashboardTabsEnum::values());

        return group();
    }

    public function htmlResponse(Group $group): Response
    {
        return Inertia::render(
            'Org/Web/WebsitesDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('Websites Dashboard'),
                'pageHead'     => [
                    'title'     => __('Websites Dashboard'),
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteDashboardTabsEnum::navigation()
                ],

                WebsiteDashboardTabsEnum::CRAWLS->value => $this->tab == WebsiteDashboardTabsEnum::CRAWLS->value ?
                    fn () => CrawlsResource::collection(IndexCrawls::make()->inGroup($group))
                    : Inertia::lazy(fn () => CrawlsResource::collection(IndexCrawls::make()->inGroup($group))),
            ]
        )->table(IndexCrawls::make()->tableStructure(parent: $group, prefix: WebsiteDashboardTabsEnum::CRAWLS->value));
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name' => 'grp.websites.index'
                    ],
                    'label' => __('Websites Dashboard'),
                ]
            ]
        ];
    }
}
