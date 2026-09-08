<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Events\BroadcastLowStockAuditLock;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleLowStockAuditLock
{
    use AsAction;

    private const int LOCK_FOREVER = 0;

    public function rules(): array
    {
        return [
            'org_stock_id'          => ['required', 'integer', Rule::exists('org_stocks', 'id')],
            'location_org_stock_id' => ['sometimes', 'nullable', 'integer', Rule::exists('location_org_stocks', 'id')],
            'is_locked'             => ['required', 'boolean'],
            'source'                => ['sometimes', 'nullable', 'string', 'in:list,detail'],
            'holder'                => ['required', 'string', 'max:64'],
        ];
    }

    public function handle(Warehouse $warehouse, array $modelData): array
    {
        $orgStockId = (int) Arr::get($modelData, 'org_stock_id');
        $holder     = (string) Arr::get($modelData, 'holder');

        $granted = Arr::get($modelData, 'is_locked')
            ? $this->acquire($orgStockId, $holder)
            : $this->release($orgStockId, $holder);

        if ($granted) {
            broadcast(new BroadcastLowStockAuditLock($warehouse, $modelData))->toOthers();
        }

        return array_merge($modelData, ['granted' => $granted]);
    }

    private function acquire(int $orgStockId, string $holder): bool
    {
        $lock = Cache::lock($this->lockKey($orgStockId), self::LOCK_FOREVER, $holder);

        // Asking twice for a lock already held is the same holder settling, not a refusal
        return $lock->get() || $lock->isOwnedByCurrentProcess();
    }

    private function release(int $orgStockId, string $holder): bool
    {
        return Cache::restoreLock($this->lockKey($orgStockId), $holder)->release();
    }

    private function lockKey(int $orgStockId): string
    {
        return 'low_stock_audit_org_stock_'.$orgStockId;
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
