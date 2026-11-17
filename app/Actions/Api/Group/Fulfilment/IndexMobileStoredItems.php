<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileStoredItems
{
    use AsController;
    use WithMobileStoredItems;

    public const array STATES = ['active', 'discontinuing', 'discontinued'];
    public const array SORTS = ['reference', 'name', 'quantity'];

    /**
     * @param array{state?: string|null, q?: string|null, fulfilment_customer_id?: int|null, pallet_id?: int|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $state      = $filters['state'] ?? 'active';
        $search     = trim((string) ($filters['q'] ?? ''));
        $sort       = $filters['sort'] ?? 'reference';
        $descending = str_starts_with($sort, '-');
        $direction  = $descending ? 'desc' : 'asc';

        return $this->filteredStoredItems($warehouse, $filters)
            ->when($state !== 'all', fn (Builder $query) => $query->where('stored_items.state', $state))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->whereStartWith('stored_items.reference', $search)
                    ->orWhereStartWith('stored_items.barcode', $search)
                    ->orWhereWith('stored_items.name', $search);
            }))
            ->with('fulfilmentCustomer.customer')
            ->orderBy(match (ltrim($sort, '-')) {
                'name'     => 'stored_items.name',
                'quantity' => 'in_warehouse.warehouse_quantity',
                default    => 'stored_items.reference',
            }, $direction)
            ->orderBy('stored_items.id', $direction)
            ->paginate($this->perPage($filters), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /**
     * @param array{fulfilment_customer_id?: int|null, pallet_id?: int|null} $filters
     */
    protected function filteredStoredItems(Warehouse $warehouse, array $filters): Builder
    {
        $customerId = $filters['fulfilment_customer_id'] ?? null;
        $palletId   = $filters['pallet_id'] ?? null;

        return $this->storedItemsInWarehouse($warehouse)
            ->when($customerId, fn (Builder $query) => $query->where('stored_items.fulfilment_customer_id', $customerId))
            ->when($palletId, fn (Builder $query) => $query->whereExists(
                $this->palletStoredItemsInWarehouse($warehouse)
                    ->where('pallet_stored_items.pallet_id', $palletId)
                    ->whereColumn('pallet_stored_items.stored_item_id', 'stored_items.id')
                    ->select('pallet_stored_items.id')
            ));
    }

    /**
     * @param array{fulfilment_customer_id?: int|null, pallet_id?: int|null} $filters
     * @return array<string, int>
     */
    private function counts(Warehouse $warehouse, array $filters): array
    {
        $byState = $this->filteredStoredItems($warehouse, $filters)
            ->select('stored_items.state')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('stored_items.state')
            ->toBase()
            ->pluck('aggregate', 'state');

        $counts = [];
        foreach (self::STATES as $state) {
            $counts[$state] = (int) ($byState[$state] ?? 0);
        }
        $counts['all'] = (int) $byState->sum();

        return $counts;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewFulfilment($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'                  => ['sometimes', 'nullable', Rule::in([...self::STATES, 'all'])],
            'q'                      => ['sometimes', 'nullable', 'string', 'max:64'],
            'fulfilment_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'pallet_id'              => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'sort'                   => ['sometimes', 'nullable', Rule::in($this->sortOptions(self::SORTS))],
            'per_page'               => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page'                   => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $storedItems = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => collect($storedItems->items())->map(fn (StoredItem $storedItem) => $this->storedItemRow($storedItem))->values()->all(),
            'meta'   => $this->paginationMeta($storedItems),
            'counts' => $this->counts($warehouse, $filters),
        ]);
    }
}
