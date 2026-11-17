<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePalletDeliveries
{
    use AsController;
    use WithMobilePalletDeliveries;

    public const array STATES = ['confirmed', 'received', 'booking_in', 'booked_in', 'not_received', 'active'];
    public const array SORTS = [
        'estimated_delivery_date' => 'pallet_deliveries.estimated_delivery_date',
        'reference'               => 'pallet_deliveries.reference',
        'booked_in_at'            => 'pallet_deliveries.booked_in_at',
    ];

    /**
     * @param array{state?: string|null, q?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $state  = $filters['state'] ?? 'active';
        $search = trim((string) ($filters['q'] ?? ''));
        $query  = PalletDelivery::query()
            ->where('pallet_deliveries.warehouse_id', $warehouse->id)
            ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', '=', 'pallet_deliveries.fulfilment_customer_id')
            ->leftJoin('customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
            ->select(['pallet_deliveries.*', 'customers.name as customer_name']);

        if ($state === 'active') {
            $query->whereIn('pallet_deliveries.state', self::ACTIVE_PALLET_DELIVERY_STATES);
        } else {
            $query->where('pallet_deliveries.state', $state);
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->whereStartWith('pallet_deliveries.reference', $search)
                    ->orWhereStartWith('pallet_deliveries.customer_reference', $search)
                    ->orWhereWith('customers.name', $search);
            });
        }

        $sort       = $filters['sort'] ?? 'estimated_delivery_date';
        $descending = str_starts_with($sort, '-');
        $column     = self::SORTS[ltrim($sort, '-')];

        return $query
            ->orderByRaw($column.($descending ? ' desc nulls last' : ' asc nulls last'))
            ->orderBy('pallet_deliveries.id', 'desc')
            ->paginate(min((int) ($filters['per_page'] ?? 0), self::MAX_PER_PAGE) ?: self::DEFAULT_PER_PAGE, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /**
     * @return array<string, int>
     */
    private function counts(Warehouse $warehouse): array
    {
        $byState = PalletDelivery::query()
            ->where('warehouse_id', $warehouse->id)
            ->toBase()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        $counts = [];
        foreach (array_diff(self::STATES, ['active']) as $state) {
            $counts[$state] = (int) $byState->get($state, 0);
        }
        $counts['active'] = collect(self::ACTIVE_PALLET_DELIVERY_STATES)->sum(fn (PalletDeliveryStateEnum $state) => $counts[$state->value]);

        return $counts;
    }

    /**
     * @param array<int, int> $palletDeliveryIds
     * @return Collection<int, object>
     */
    private function palletCounts(array $palletDeliveryIds): Collection
    {
        if (!$palletDeliveryIds) {
            return collect();
        }

        $notYetBookedIn = collect(self::NOT_YET_BOOKED_IN_PALLET_STATES)->map(fn (PalletStateEnum $state) => $state->value)->all();

        return Pallet::query()
            ->whereIn('pallet_delivery_id', $palletDeliveryIds)
            ->toBase()
            ->selectRaw('pallet_delivery_id')
            ->selectRaw('count(*) filter (where type = ?) as pallets', [PalletTypeEnum::PALLET->value])
            ->selectRaw('count(*) filter (where type = ?) as boxes', [PalletTypeEnum::BOX->value])
            ->selectRaw('count(*) filter (where type = ?) as oversizes', [PalletTypeEnum::OVERSIZE->value])
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where state not in ('.implode(', ', array_fill(0, count($notYetBookedIn), '?')).')) as booked_in', $notYetBookedIn)
            ->selectRaw('count(*) filter (where state = ?) as not_received', [PalletStateEnum::NOT_RECEIVED->value])
            ->groupBy('pallet_delivery_id')
            ->get()
            ->keyBy('pallet_delivery_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $palletDeliveries): array
    {
        $listed       = collect($palletDeliveries->items());
        $palletCounts = $this->palletCounts($listed->pluck('id')->all());

        return $listed->map(function (PalletDelivery $palletDelivery) use ($palletCounts) {
            $counts = $palletCounts->get($palletDelivery->id);

            return [
                'id'                      => $palletDelivery->id,
                'reference'               => $palletDelivery->reference,
                'customer_reference'      => $palletDelivery->customer_reference,
                'state'                   => $palletDelivery->state->value,
                'state_label'             => PalletDeliveryStateEnum::labels()[$palletDelivery->state->value],
                'customer'                => ['id' => $palletDelivery->fulfilment_customer_id, 'name' => $palletDelivery->getAttribute('customer_name')],
                'estimated_delivery_date' => $this->palletDeliveryDate($palletDelivery->estimated_delivery_date),
                'received_at'             => $this->isoDateTime($palletDelivery->received_at),
                'booked_in_at'            => $this->isoDateTime($palletDelivery->booked_in_at),
                'counts'                  => [
                    'pallets'      => (int) ($counts->pallets ?? 0),
                    'boxes'        => (int) ($counts->boxes ?? 0),
                    'oversizes'    => (int) ($counts->oversizes ?? 0),
                    'total'        => (int) ($counts->total ?? 0),
                    'booked_in'    => (int) ($counts->booked_in ?? 0),
                    'not_received' => (int) ($counts->not_received ?? 0),
                ],
            ];
        })->values()->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletDeliveries($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'    => ['sometimes', 'nullable', Rule::in(self::STATES)],
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'     => ['sometimes', 'nullable', Rule::in(collect(array_keys(self::SORTS))->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $palletDeliveries = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => $this->rows($palletDeliveries),
            'meta'   => $this->paginationMeta($palletDeliveries),
            'counts' => $this->counts($warehouse),
        ]);
    }
}
