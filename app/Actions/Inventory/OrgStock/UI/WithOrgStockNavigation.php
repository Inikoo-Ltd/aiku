<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 21:19:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Inventory\OrgStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithOrgStockNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var OrgStock $orgStock */
        $orgStock = $model;

        if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show') {
            $query->where('org_stock_family_id', $orgStock->orgStockFamily->id);
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var OrgStock $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var OrgStock $orgStock */
        $orgStock = $model;

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show',
            'grp.org.warehouses.show.inventory.org-stocks.show',
            'grp.org.warehouses.show.inventory.org_stock_movements.index' => [
                'organisation' => $orgStock->organisation->slug,
                'warehouse'    => $this->warehouse->slug,
                'orgStock'     => $orgStock->slug
            ],
            'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show' => [
                'organisation'   => $orgStock->organisation->slug,
                'warehouse'      => $this->warehouse->slug,
                'orgStockFamily' => $orgStock->orgStockFamily->slug,
                'orgStock'       => $orgStock->slug
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
