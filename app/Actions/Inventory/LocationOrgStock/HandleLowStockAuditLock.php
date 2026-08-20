<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Events\BroadcastLowStockAuditLock;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleLowStockAuditLock
{
    use AsAction;

    public function rules(): array
    {
        return [
            'org_stock_id'          => ['required', 'integer', Rule::exists('org_stocks', 'id')],
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer', Rule::exists('location_org_stocks', 'id')],
            'is_locked'             => ['required', 'boolean'],
            'source'                => ['sometimes', 'nullable', 'string', 'in:list,detail'],
        ];
    }

    public function handle(Warehouse $warehouse, array $modelData): array
    {
        broadcast(new BroadcastLowStockAuditLock($warehouse, $modelData))->toOthers();

        return $modelData;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): array
    {
        return $this->handle($warehouse, $request->validated());
    }

    public function jsonResponse(array $modelData): JsonResponse
    {
        return response()->json($modelData);
    }
}
