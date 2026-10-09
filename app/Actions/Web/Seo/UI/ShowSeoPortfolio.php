<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Seo\GetSeoPortfolio;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoPortfolio extends OrgAction
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
        $title = __('SEO portfolio');

        return Inertia::render(
            'Org/Web/SeoPortfolio',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-globe'],
                        'title' => $title,
                    ],
                ],
                'data'        => GetSeoPortfolio::run(),
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
                    'route' => ['name' => 'grp.websites.seo.portfolio'],
                    'label' => __('SEO portfolio'),
                ],
            ],
        ];
    }
}
