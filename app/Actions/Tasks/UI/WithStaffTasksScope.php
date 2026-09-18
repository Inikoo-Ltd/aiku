<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithStaffTasksScope
{
    protected function initialisationFromTasksScope(ActionRequest $request, ?Organisation $organisation = null, ?Shop $shop = null): static
    {
        if ($shop) {
            return $this->initialisationFromShop($shop, $request);
        }

        if ($organisation) {
            return $this->initialisation($organisation, $request);
        }

        return $this->initialisationFromGroup(group(), $request);
    }

    /**
     * @return array{name: string, parameters: array<int, string>}
     */
    protected function tasksRoute(string $suffix): array
    {
        if (isset($this->shop)) {
            return [
                'name'       => 'grp.org.shops.show.tasks.'.$suffix,
                'parameters' => [$this->organisation->slug, $this->shop->slug],
            ];
        }

        if (isset($this->organisation)) {
            return [
                'name'       => 'grp.org.tasks.'.$suffix,
                'parameters' => [$this->organisation->slug],
            ];
        }

        return [
            'name'       => 'grp.tasks.'.$suffix,
            'parameters' => [],
        ];
    }

    protected function tasksBreadcrumbs(): array
    {
        $parentBreadcrumbs = match (true) {
            isset($this->shop) => ShowShop::make()->getBreadcrumbs([
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
            ]),
            isset($this->organisation) => ShowOrganisationDashboard::make()->getBreadcrumbs([
                'organisation' => $this->organisation->slug,
            ]),
            default => ShowGroupDashboard::make()->getBreadcrumbs(),
        };

        return array_merge(
            $parentBreadcrumbs,
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-tasks',
                        'route' => $this->tasksRoute('index'),
                        'label' => __('Tasks'),
                    ],
                ],
            ]
        );
    }
}
