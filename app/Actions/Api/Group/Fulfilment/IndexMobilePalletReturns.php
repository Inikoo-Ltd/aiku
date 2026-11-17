<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePalletReturns
{
    use AsController;
    use WithMobilePalletReturnLines;

    public const array STATES = ['confirmed', 'picking', 'picked', 'dispatched', 'cancel'];
    public const array ACTIVE_STATES = ['confirmed', 'picking', 'picked'];
    public const array TYPE_GROUPS = ['pallets', 'items'];
    public const array SORTS = ['date', 'reference', 'dispatched_at'];

    /**
     * @param array{state?: string|null, type?: string|null, q?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $search     = trim((string) ($filters['q'] ?? ''));
        $sort       = $filters['sort'] ?? '-date';
        $descending = str_starts_with($sort, '-');
        $direction  = $descending ? 'desc' : 'asc';
        $sortColumn = match (ltrim($sort, '-')) {
            'reference'     => 'pallet_returns.reference',
            'dispatched_at' => 'pallet_returns.dispatched_at',
            default         => 'COALESCE(pallet_returns.confirmed_at, pallet_returns.date)',
        };

        return $this->scopedReturns($warehouse, $filters['state'] ?? 'active', $filters['type'] ?? null)
            ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', '=', 'pallet_returns.fulfilment_customer_id')
            ->leftJoin('customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
            ->select('pallet_returns.*')
            ->selectRaw('COALESCE(pallet_returns.confirmed_at, pallet_returns.date) as listed_date')
            ->selectSub(PalletReturnItem::query()->whereColumn('pallet_return_items.pallet_return_id', 'pallet_returns.id')->selectRaw('COUNT(DISTINCT pallet_return_items.pallet_id)'), 'number_pallets')
            ->selectSub(PalletReturnItem::query()->whereColumn('pallet_return_items.pallet_return_id', 'pallet_returns.id')->where('pallet_return_items.type', 'StoredItem')->selectRaw('COUNT(*)'), 'number_items')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->whereStartWith('pallet_returns.reference', $search)
                    ->orWhereStartWith('pallet_returns.customer_reference', $search)
                    ->orWhereStartWith('pallet_returns.slug', $search)
                    ->orWhereWith('customers.name', $search);
            }))
            ->with(['fulfilmentCustomer.customer', 'platform', 'pickerUser'])
            ->orderByRaw("$sortColumn $direction NULLS LAST")
            ->orderBy('pallet_returns.id', $direction)
            ->paginate($this->perPage($filters), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    protected function scopedReturns(Warehouse $warehouse, ?string $state, ?string $typeGroup): Builder
    {
        return PalletReturn::query()
            ->where('pallet_returns.warehouse_id', $warehouse->id)
            ->when($state !== null, fn (Builder $query) => $query->whereIn('pallet_returns.state', $state === 'active' ? self::ACTIVE_STATES : [$state]))
            ->when($typeGroup === 'pallets', fn (Builder $query) => $query->where('pallet_returns.type', PalletReturnTypeEnum::PALLET))
            ->when($typeGroup === 'items', fn (Builder $query) => $query->where('pallet_returns.type', '!=', PalletReturnTypeEnum::PALLET));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $palletReturns): array
    {
        return collect($palletReturns->items())->map(fn (PalletReturn $palletReturn) => [
            'id'                 => $palletReturn->id,
            'reference'          => $palletReturn->reference,
            'customer_reference' => $palletReturn->customer_reference,
            'type'               => $palletReturn->type->value,
            'type_group'         => $this->typeGroup($palletReturn->type),
            'type_label'         => $this->typeGroupLabel($palletReturn->type),
            'platform'           => $palletReturn->platform ? ['code' => $palletReturn->platform->code, 'name' => $palletReturn->platform->name] : null,
            'state'              => $palletReturn->state->value,
            'state_label'        => PalletReturnStateEnum::labels()[$palletReturn->state->value],
            'customer'           => $this->customerReference($palletReturn->fulfilmentCustomer),
            'number_pallets'     => (int) $palletReturn->getAttribute('number_pallets'),
            'number_items'       => (int) $palletReturn->getAttribute('number_items'),
            'date'               => $this->isoDateTime($palletReturn->getAttribute('listed_date')),
            'picker'             => $this->userReference($palletReturn->pickerUser),
            'is_collection'      => (bool) $palletReturn->is_collection,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function counts(Warehouse $warehouse, string $state, ?string $typeGroup): array
    {
        $byState = $this->scopedReturns($warehouse, null, $typeGroup)
            ->whereIn('pallet_returns.state', self::STATES)
            ->selectRaw('pallet_returns.state, COUNT(*) as aggregate')
            ->groupBy('pallet_returns.state')
            ->toBase()
            ->pluck('aggregate', 'state');

        $byType = $this->scopedReturns($warehouse, $state, null)
            ->selectRaw("CASE WHEN pallet_returns.type = ? THEN 'pallets' ELSE 'items' END as type_group, COUNT(*) as aggregate", [PalletReturnTypeEnum::PALLET->value])
            ->groupBy(DB::raw('1'))
            ->toBase()
            ->pluck('aggregate', 'type_group');

        $counts = [];
        foreach (self::STATES as $stateCounted) {
            $counts[$stateCounted] = (int) ($byState[$stateCounted] ?? 0);
        }
        $counts['active'] = array_sum(array_map(fn (string $activeState) => $counts[$activeState], self::ACTIVE_STATES));
        $counts['types']  = [
            'pallets' => (int) ($byType['pallets'] ?? 0),
            'items'   => (int) ($byType['items'] ?? 0),
        ];

        return $counts;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewFulfilment($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'    => ['sometimes', 'nullable', Rule::in([...self::STATES, 'active'])],
            'type'     => ['sometimes', 'nullable', Rule::in(self::TYPE_GROUPS)],
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'     => ['sometimes', 'nullable', Rule::in($this->sortOptions(self::SORTS))],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $palletReturns = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => $this->rows($palletReturns),
            'meta'   => $this->paginationMeta($palletReturns),
            'counts' => $this->counts($warehouse, $filters['state'] ?? 'active', $filters['type'] ?? null),
        ]);
    }
}
