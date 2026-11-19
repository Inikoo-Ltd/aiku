<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileStockDeliveries
{
    use AsController;
    use WithMobileStockDeliveries;

    public const array STATES = ['dispatched', 'received', 'checked', 'booking_in', 'booked_in', 'placed', 'not_received', 'cancelled', 'active'];
    public const array DISPATCHED_BUCKETS = ['today', 'week', 'older'];
    public const array SORTS = [
        'date'          => 'stock_deliveries.date',
        'dispatched_at' => 'stock_deliveries.dispatched_at',
        'reference'     => 'stock_deliveries.reference',
        'number_items'  => 'stock_deliveries.number_stock_delivery_items_except_cancelled',
    ];

    /**
     * @param array{state?: string|null, source?: string|null, q?: string|null, dispatched?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $query  = $this->sourceQuery($warehouse, $filters['source'] ?? null);
        $state  = $filters['state'] ?? 'active';
        $search = trim((string) ($filters['q'] ?? ''));

        if ($state === 'active') {
            $query->whereIn('stock_deliveries.state', self::ACTIVE_STOCK_DELIVERY_STATES);
        } else {
            $query->where('stock_deliveries.state', $state);
        }

        if ($search !== '') {
            IndexStockDeliveries::whereGlobalSearch($query, $search);
        }

        if ($dispatched = $filters['dispatched'] ?? null) {
            $this->whereDispatched($query, $dispatched);
        }

        $sort       = $filters['sort'] ?? '-date';
        $descending = str_starts_with($sort, '-');
        $column     = self::SORTS[ltrim($sort, '-')];

        return $query
            ->orderByRaw($column.($descending ? ' desc nulls last' : ' asc nulls last'))
            ->orderBy('stock_deliveries.id', $descending ? 'desc' : 'asc')
            ->paginate(min((int) ($filters['per_page'] ?? 0), self::WEB_MAX_PER_PAGE) ?: (int) config('ui.table.records_per_page'), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public static function numberInState(Warehouse $warehouse, string $state): int
    {
        return (new self())->sourceQuery($warehouse, null)->where('stock_deliveries.state', $state)->count();
    }

    private function sourceQuery(Warehouse $warehouse, ?string $source): Builder
    {
        return StockDelivery::query()
            ->where('stock_deliveries.organisation_id', $warehouse->organisation_id)
            ->when($source, fn (Builder $query) => $query->whereIn('stock_deliveries.parent_type', self::STOCK_DELIVERY_SOURCES[$source]));
    }

    private function whereDispatched(Builder $query, string $bucket): void
    {
        $today    = now()->startOfDay();
        $thisWeek = now()->subDays(6)->startOfDay();

        match ($bucket) {
            'today' => $query->where('stock_deliveries.dispatched_at', '>=', $today),
            'week'  => $query->where('stock_deliveries.dispatched_at', '>=', $thisWeek),
            default => $query->where('stock_deliveries.dispatched_at', '<', $thisWeek),
        };
    }

    /**
     * @return array<string, int>
     */
    private function counts(Warehouse $warehouse, ?string $source): array
    {
        $byState = $this->sourceQuery($warehouse, $source)
            ->toBase()
            ->selectRaw('stock_deliveries.state, count(*) as total')
            ->groupBy('stock_deliveries.state')
            ->pluck('total', 'state');

        $counts = [];
        foreach (array_diff(self::STATES, ['active']) as $state) {
            $counts[$state] = (int) $byState->get($state, 0);
        }
        $counts['active'] = collect(self::ACTIVE_STOCK_DELIVERY_STATES)->sum(fn (StockDeliveryStateEnum $state) => $counts[$state->value]);

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $stockDeliveries): array
    {
        return collect($stockDeliveries->items())->map(fn (StockDelivery $stockDelivery) => [
            'id'                       => $stockDelivery->id,
            'reference'                => $stockDelivery->reference,
            'agent_reference'          => $stockDelivery->agent_reference,
            'supplier'                 => $this->stockDeliverySupplier($stockDelivery->parent_type, $stockDelivery->parent_code, $stockDelivery->parent_name),
            'source'                   => self::stockDeliverySource($stockDelivery->parent_type),
            'state'                    => $stockDelivery->state->value,
            'state_label'              => StockDeliveryStateEnum::labels()[$stockDelivery->state->value],
            'dispatched_at'            => $this->isoDateTime($stockDelivery->dispatched_at),
            'received_at'              => $this->isoDateTime($stockDelivery->received_at),
            'estimated_receiving_date' => $this->estimatedReceivingDate($stockDelivery->data['estimated_receiving_date'] ?? null),
            'number_items'             => (int) $stockDelivery->number_stock_delivery_items_except_cancelled,
            'number_checked'           => (int) $stockDelivery->number_stock_delivery_items_state_checked + (int) $stockDelivery->number_stock_delivery_items_state_placed + (int) $stockDelivery->number_stock_delivery_items_state_not_received,
            'number_placed'            => (int) $stockDelivery->number_stock_delivery_items_state_placed,
        ])->values()->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewStockDeliveries($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'      => ['sometimes', 'nullable', Rule::in(self::STATES)],
            'source'     => ['sometimes', 'nullable', Rule::in(array_keys(self::STOCK_DELIVERY_SOURCES))],
            'q'          => ['sometimes', 'nullable', 'string', 'max:64'],
            'dispatched' => ['sometimes', 'nullable', Rule::in(self::DISPATCHED_BUCKETS)],
            'sort'       => ['sometimes', 'nullable', Rule::in(collect(array_keys(self::SORTS))->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'per_page'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'       => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $stockDeliveries = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => $this->rows($stockDeliveries),
            'meta'   => $this->paginationMeta($stockDeliveries),
            'counts' => $this->counts($warehouse, $filters['source'] ?? null),
        ]);
    }
}
