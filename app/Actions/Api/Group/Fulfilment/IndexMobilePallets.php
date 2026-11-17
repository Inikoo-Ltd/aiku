<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePallets
{
    use AsController;
    use WithMobileStoredPallets;

    public const array FILTERS = ['storing', 'receiving', 'returning', 'incidents', 'returned'];
    public const array RECEIVING_STATES = [PalletStateEnum::RECEIVED, PalletStateEnum::BOOKING_IN, PalletStateEnum::BOOKED_IN];
    public const array INCIDENT_STATES = [PalletStateEnum::DAMAGED, PalletStateEnum::LOST, PalletStateEnum::OTHER_INCIDENT];
    public const array SORTS = [
        'reference'          => 'pallets.reference',
        'customer_reference' => 'pallets.customer_reference',
        'location_code'      => 'locations.code',
        'incident_date'      => 'pallets.set_as_incident_at',
    ];

    /**
     * @param array{filter?: string|null, incident_state?: string|null, q?: string|null, location_id?: int|null, fulfilment_customer_id?: int|null, type?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $filter = $filters['filter'] ?? 'storing';
        $search = trim((string) ($filters['q'] ?? ''));
        $query  = $this->scopedQuery($warehouse, $filters)
            ->leftJoin('locations', 'locations.id', '=', 'pallets.location_id')
            ->select('pallets.*');

        match ($filter) {
            'receiving' => $query->whereIn('pallets.state', self::RECEIVING_STATES),
            'returning' => $query->where('pallets.status', PalletStatusEnum::RETURNING),
            'incidents' => $query->where('pallets.status', PalletStatusEnum::INCIDENT)
                ->when($filters['incident_state'] ?? null, fn (Builder $query, string $state) => $query->where('pallets.state', $state)),
            'returned'  => $query->where('pallets.status', PalletStatusEnum::RETURNED),
            default     => $query->where('pallets.status', PalletStatusEnum::STORING),
        };

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->whereStartWith('pallets.reference', $search)
                    ->orWhereWith('pallets.customer_reference', $search)
                    ->orWhereStartWith('locations.code', $search);
            });
        }

        $sort       = $filters['sort'] ?? 'reference';
        $descending = str_starts_with($sort, '-');
        $column     = self::SORTS[ltrim($sort, '-')];

        return $query
            ->orderByRaw($column.($descending ? ' desc nulls last' : ' asc nulls last'))
            ->orderBy('pallets.id', $descending ? 'desc' : 'asc')
            ->paginate(min((int) ($filters['per_page'] ?? 0), self::MAX_PER_PAGE) ?: self::DEFAULT_PER_PAGE, ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /**
     * @param array{location_id?: int|null, fulfilment_customer_id?: int|null, type?: string|null} $filters
     */
    private function scopedQuery(Warehouse $warehouse, array $filters): Builder
    {
        return Pallet::query()
            ->where('pallets.warehouse_id', $warehouse->id)
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $locationId) => $query->where('pallets.location_id', $locationId))
            ->when($filters['fulfilment_customer_id'] ?? null, fn (Builder $query, int $fulfilmentCustomerId) => $query->where('pallets.fulfilment_customer_id', $fulfilmentCustomerId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('pallets.type', $type));
    }

    /**
     * @param array{location_id?: int|null, fulfilment_customer_id?: int|null, type?: string|null} $filters
     * @return array<string, mixed>
     */
    private function counts(Warehouse $warehouse, array $filters): array
    {
        $receivingStates = collect(self::RECEIVING_STATES)->map(fn (PalletStateEnum $state) => $state->value)->all();
        $incident        = PalletStatusEnum::INCIDENT->value;

        $counts = $this->scopedQuery($warehouse, $filters)
            ->toBase()
            ->selectRaw('count(*) filter (where status = ?) as storing', [PalletStatusEnum::STORING->value])
            ->selectRaw('count(*) filter (where state in (?, ?, ?)) as receiving', $receivingStates)
            ->selectRaw('count(*) filter (where status = ?) as returning', [PalletStatusEnum::RETURNING->value])
            ->selectRaw('count(*) filter (where status = ?) as incidents', [$incident])
            ->selectRaw('count(*) filter (where status = ?) as returned', [PalletStatusEnum::RETURNED->value])
            ->selectRaw('count(*) filter (where status = ? and state = ?) as damaged', [$incident, PalletStateEnum::DAMAGED->value])
            ->selectRaw('count(*) filter (where status = ? and state = ?) as lost', [$incident, PalletStateEnum::LOST->value])
            ->selectRaw('count(*) filter (where status = ? and state = ?) as other_incident', [$incident, PalletStateEnum::OTHER_INCIDENT->value])
            ->first();

        return [
            'storing'         => (int) $counts->storing,
            'receiving'       => (int) $counts->receiving,
            'returning'       => (int) $counts->returning,
            'incidents'       => (int) $counts->incidents,
            'returned'        => (int) $counts->returned,
            'incident_states' => [
                'damaged'        => (int) $counts->damaged,
                'lost'           => (int) $counts->lost,
                'other_incident' => (int) $counts->other_incident,
            ],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPallets($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'filter'                 => ['sometimes', 'nullable', Rule::in(self::FILTERS)],
            'incident_state'         => ['sometimes', 'nullable', Rule::in(collect(self::INCIDENT_STATES)->map(fn (PalletStateEnum $state) => $state->value)->all())],
            'q'                      => ['sometimes', 'nullable', 'string', 'max:64'],
            'location_id'            => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'fulfilment_customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'type'                   => ['sometimes', 'nullable', Rule::enum(PalletTypeEnum::class)],
            'sort'                   => ['sometimes', 'nullable', Rule::in(collect(array_keys(self::SORTS))->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'per_page'               => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page'                   => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        foreach (['location_id', 'fulfilment_customer_id'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }

        $pallets = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => $this->palletRows($this->withPalletRowRelations(new EloquentCollection($pallets->items()))),
            'meta'   => $this->paginationMeta($pallets),
            'counts' => $this->counts($warehouse, $filters),
        ]);
    }
}
