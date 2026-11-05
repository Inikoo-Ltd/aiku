<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SysAdmin\Group;

trait WithCompetitorsSubNavigation
{
    protected function getCompetitorsSubNavigation(Group $group): array
    {
        return [
            [
                'label'    => __('Dashboard'),
                'root'     => 'grp.masters.competitors.dashboard',
                'route'    => ['name' => 'grp.masters.competitors.dashboard', 'parameters' => []],
                'leftIcon' => ['icon' => ['fal', 'fa-tachometer-alt-fast'], 'tooltip' => __('Dashboard')],
            ],
            [
                'label'  => __('Competitors'),
                'root'   => 'grp.masters.competitors.index',
                'route'  => ['name' => 'grp.masters.competitors.index', 'parameters' => []],
                'number' => Competitor::where('group_id', $group->id)->count(),
            ],
            [
                'label'  => __('Prices to review'),
                'root'   => 'grp.masters.competitors.prices.',
                'route'  => ['name' => 'grp.masters.competitors.prices.index', 'parameters' => []],
                'number' => MasterAssetCompetitorProduct::whereHas('masterAsset', fn ($query) => $query->where('master_assets.group_id', $group->id))
                    ->where('master_asset_competitor_products.status', MasterAssetCompetitorProductStatusEnum::SUGGESTED)->count(),
            ],
        ];
    }

    public function getCompetitorsBreadcrumbs(?string $routeName = null, ?string $label = null): array
    {
        $crumbs = [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.masters.competitors.dashboard', 'parameters' => []],
                    'label' => __('Competitors'),
                    'icon'  => 'fal fa-binoculars',
                ],
            ],
        ];
        if ($routeName) {
            $crumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => $routeName, 'parameters' => []],
                    'label' => $label,
                ],
            ];
        }

        return array_merge(ShowMastersDashboard::make()->getBreadcrumbs(), $crumbs);
    }
}
