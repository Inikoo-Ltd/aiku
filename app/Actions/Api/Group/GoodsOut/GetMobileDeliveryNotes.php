<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileDeliveryNotes
{
    use AsController;
    use WithMobileDeliveryNotes;
    use WithMobileLocationsAccess;

    public const array SORTS = ['date', 'number_items', 'effective_weight', 'customer_name'];

    public const int DEFAULT_PER_PAGE = 50;

    public const int MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $filters
     */
    public function handle(Warehouse $warehouse, User $user, array $filters): LengthAwarePaginator
    {
        $this->useWebDeliveryNotesParameters($filters);

        $query = $this->deliveryNotesQuery(
            warehouse: $warehouse,
            bucket: $filters['bucket'] ?? 'unassigned',
            channel: $filters['channel'] ?? 'all',
            picker: $filters['picker'] ?? null,
            user: $user
        );

        return $this->withRowColumns($query)->paginate(
            perPage: (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function useWebDeliveryNotesParameters(array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $from   = $filters['from'] ?? null;
        $to     = $filters['to'] ?? null;

        request()->merge(array_filter([
            'sort'    => $filters['sort'] ?? null,
            'filter'  => $search === '' ? null : ['global' => $search],
            'between' => $from || $to ? ['date' => str_replace('-', '', $from ?? '1970-01-01').'-'.str_replace('-', '', $to ?? '2099-12-31')] : null,
        ]));
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && IndexDeliveryNotes::canViewDispatching($user, $warehouse), 403);

        $sorts = collect(self::SORTS)->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all();

        $filters = Validator::make($request->query(), [
            'bucket'   => ['sometimes', 'nullable', Rule::in(array_keys(self::BUCKETS))],
            'channel'  => ['sometimes', 'nullable', Rule::in(self::CHANNELS)],
            'search'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'from'     => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to'       => array_merge(['sometimes', 'nullable', 'date_format:Y-m-d'], $request->filled('from') ? ['after_or_equal:from'] : []),
            'picker'   => ['sometimes', 'nullable', Rule::in(self::PICKER_FILTERS)],
            'sort'     => ['sometimes', 'nullable', Rule::in($sorts)],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ])->validate();

        $deliveryNotes = $this->handle($warehouse, $user, $filters);
        $canWork       = $warehouse->canBeWorkedInBy($user);

        return response()->json([
            'data'       => [
                'delivery_notes'        => collect($deliveryNotes->items())->map(fn (DeliveryNote $deliveryNote) => [
                    ...$this->deliveryNoteRow($deliveryNote),
                    'can_add_to_picking_session' => $canWork && $this->isPickingSessionCandidate($deliveryNote),
                ])->all(),
                'can_work_in_warehouse' => $canWork,
            ],
            'pagination' => $this->paginationMeta($deliveryNotes),
        ]);
    }
}
