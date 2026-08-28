<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;

trait WithOrgStockReplenishments
{
    public const SCOPE_WHOLESALE    = 'wholesale';
    public const SCOPE_DROPSHIPPING = 'dropshipping';

    protected function getActivePickingLocationColumn(string $scope): string
    {
        return $scope == self::SCOPE_DROPSHIPPING
            ? 'default_dropshipping_picking_location'
            : 'default_wholesale_picking_location';
    }

    protected function applyReplenishmentsConstraints($queryBuilder, Organisation $organisation, string $scope): void
    {
        $activePickingLocations = DB::table('location_org_stocks')
            ->selectRaw('DISTINCT ON (org_stock_id) org_stock_id, location_id, quantity, settings')
            ->where($this->getActivePickingLocationColumn($scope), true)
            ->orderByRaw('org_stock_id, picking_priority NULLS LAST, id');

        $queryBuilder->where('org_stocks.organisation_id', $organisation->id);
        $queryBuilder->where('org_stocks.state', OrgStockStateEnum::ACTIVE);
        $queryBuilder->joinSub($activePickingLocations, 'active_location', 'active_location.org_stock_id', '=', 'org_stocks.id');
        $queryBuilder->where(function ($query) {
            $query->whereRaw("(active_location.settings->>'min_stock') IS NOT NULL and active_location.quantity < (active_location.settings->>'min_stock')::numeric")
                ->orWhereRaw("(active_location.settings->>'min_stock') IS NULL and active_location.quantity <= 0");
        });
        $queryBuilder->whereExists(function ($query) {
            $query->from('location_org_stocks as other_locations')
                ->whereColumn('other_locations.org_stock_id', 'org_stocks.id')
                ->whereColumn('other_locations.location_id', '!=', 'active_location.location_id')
                ->where('other_locations.quantity', '>', 0);
        });
    }

    protected function countReplenishments(Organisation $organisation, string $scope): int
    {
        $queryBuilder = OrgStock::query();

        $this->applyReplenishmentsConstraints($queryBuilder, $organisation, $scope);

        return $queryBuilder->count();
    }
}
