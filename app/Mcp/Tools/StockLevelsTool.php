<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Search stock items in a warehouse by code or name, returning quantity on hand and value.')]
class StockLevelsTool extends AikuWarehouseTool
{
    protected function permission(): WarehousePermissionsEnum
    {
        return WarehousePermissionsEnum::STOCKS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'warehouse' => ['required', 'string'],
            'query'     => ['required', 'string'],
        ]);

        $warehouse = $this->authorisedWarehouse($request);
        if (!$warehouse) {
            return Response::error('Warehouse not found or permission denied.');
        }

        $query = '%'.$request->string('query').'%';

        $stocks = OrgStock::where('organisation_id', $warehouse->organisation_id)
            ->where(function ($q) use ($query) {
                $q->where('code', 'ilike', $query)
                    ->orWhere('name', 'ilike', $query);
            })
            ->limit(20)
            ->get(['code', 'name', 'quantity_in_locations', 'value_in_locations', 'state']);

        return Response::json([
            'warehouse' => $warehouse->name,
            'results'   => $stocks->map(function ($stock) {
                return [
                    'code'                   => $stock->code,
                    'name'                   => $stock->name,
                    'quantity_in_locations'  => $stock->quantity_in_locations,
                    'value_in_locations'     => $stock->value_in_locations,
                    'state'                  => $stock->state->value,
                ];
            })->values(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'warehouse' => $schema->string()->description('Warehouse slug')->required(),
            'query'     => $schema->string()->description('Search text matched against stock code or name')->required(),
        ];
    }
}
