<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Seo\GetSeoApiUsage;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The SEO API spend of all shops together against the one monthly budget, at group level next to the
 * SEO portfolio.
 */
class ShowSeoApiUsage extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request);

        return group();
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('SEO API usage');
        $month = (string) $request->query('month');

        return Inertia::render(
            'Org/Web/SeoApiUsage',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-tachometer-alt'],
                        'title' => $title,
                    ],
                ],
                'data'        => [
                    'usage'        => GetSeoApiUsage::run(preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m-d', $month.'-01') : now()),
                    'can_edit'     => $request->user()->authTo(['group-webmaster.edit', 'sysadmin.edit']),
                    'budget_route' => [
                        'name'       => 'grp.models.group.seo_api_budget.update',
                        'parameters' => [],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...WebsitesDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.websites.seo.api_usage'],
                    'label' => __('SEO API usage'),
                ],
            ],
        ];
    }
}
