<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Traits;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithGroupModuleScope
{
    protected function initialisationFromModuleScope(ActionRequest $request, ?Organisation $organisation = null, ?Shop $shop = null): static
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
     * @param  array<int, string>  $extraParameters
     *
     * @return array{name: string, parameters: array<int, string>}
     */
    protected function moduleScopeRoute(string $module, string $suffix, array $extraParameters = []): array
    {
        if (isset($this->shop)) {
            return [
                'name'       => "grp.org.shops.show.$module.$suffix",
                'parameters' => [$this->organisation->slug, $this->shop->slug, ...$extraParameters],
            ];
        }

        if (isset($this->organisation)) {
            return [
                'name'       => "grp.org.$module.$suffix",
                'parameters' => [$this->organisation->slug, ...$extraParameters],
            ];
        }

        return [
            'name'       => "grp.$module.$suffix",
            'parameters' => $extraParameters,
        ];
    }

    protected function moduleScopeParentBreadcrumbs(): array
    {
        return match (true) {
            isset($this->shop) => ShowShop::make()->getBreadcrumbs([
                'organisation' => $this->organisation->slug,
                'shop'         => $this->shop->slug,
            ]),
            isset($this->organisation) => ShowOrganisationDashboard::make()->getBreadcrumbs([
                'organisation' => $this->organisation->slug,
            ]),
            default => ShowGroupDashboard::make()->getBreadcrumbs(),
        };
    }
}
