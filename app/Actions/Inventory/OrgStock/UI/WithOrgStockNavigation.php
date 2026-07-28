<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 21:19:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
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

        $query->where('organisation_id', $orgStock->organisation_id);

        if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show') {
            $query->where('org_stock_family_id', $orgStock->org_stock_family_id);
        }

        if (str_contains($request->route()->getName(), 'grp.org.warehouses.show.inventory.org_stocks.')) {
            $query->where('organisation_id', $orgStock->organisation_id);
        }

        if (!$request->input('bucket') && preg_match('/\.(\w+)_org_stocks\./', $request->route()->getName(), $matches)) {
            $this->applyNavigationBucket($query, $matches[1], $model, $request);
        }
    }

    protected function applyNavigationBucket(Builder $query, string $bucket, Model $model, ActionRequest $request): void
    {
        if ($bucket == 'current') {
            $query->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE, OrgStockStateEnum::DISCONTINUING]);
        } elseif ($bucket == 'active') {
            $query->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        } elseif ($bucket == 'discontinuing') {
            $query->where('org_stocks.state', OrgStockStateEnum::DISCONTINUING);
        } elseif ($bucket == 'discontinued') {
            $query->where('org_stocks.state', OrgStockStateEnum::DISCONTINUED);
        } elseif ($bucket == 'abnormality') {
            $query->where('org_stocks.state', OrgStockStateEnum::ABNORMALITY);
        }
    }

    protected function getNavigationDefaultSort(Model $model): array
    {
        return ['org_stocks.code', false];
    }

    protected function getNavigationSortColumns(Model $model): array
    {
        return [
            'code'                           => 'org_stocks.code',
            'name'                           => 'org_stocks.name',
            'discontinued_in_organisation_at' => 'org_stocks.discontinued_in_organisation_at',
        ];
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
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.active_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.in_process_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.discontinuing_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.show',
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
