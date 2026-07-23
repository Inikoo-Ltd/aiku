<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Counts of delivery notes by state for a warehouse over a date range.')]
class DeliveryNotesSummaryTool extends AikuWarehouseTool
{
    protected function permission(): WarehousePermissionsEnum
    {
        return WarehousePermissionsEnum::DISPATCHING_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'warehouse' => ['required', 'string'],
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
        ]);

        $warehouse = $this->authorisedWarehouse($request);
        if (!$warehouse) {
            return Response::error('Warehouse not found or permission denied.');
        }

        $notes = DeliveryNote::where('warehouse_id', $warehouse->id)
            ->whereBetween('date', [$request->date('from'), $request->date('to')->endOfDay()])
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')
            ->get();

        $states = [];
        $total  = 0;

        foreach ($notes as $note) {
            $states[$note->state->value] = (int) $note->count;
            $total                        += $note->count;
        }

        return Response::json([
            'warehouse' => $warehouse->name,
            'from'      => $request->string('from'),
            'to'        => $request->string('to'),
            'states'    => $states,
            'total'     => $total,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'warehouse' => $schema->string()->description('Warehouse slug')->required(),
            'from'      => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'        => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
