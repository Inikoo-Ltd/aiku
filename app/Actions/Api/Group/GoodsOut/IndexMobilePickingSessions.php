<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePickingSessions
{
    use AsController;
    use WithMobilePickingSessions;

    public const int PER_PAGE = 25;

    public const array SORTS = [
        'reference' => 'picking_sessions.reference',
        'user_name' => 'users.contact_name',
        'start_at'  => 'picking_sessions.start_at',
        'end_at'    => 'picking_sessions.end_at',
    ];

    /**
     * @param array{state?: string|null, q?: string|null, sort?: string|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $state  = $filters['state'] ?? null;
        $search = trim((string) ($filters['q'] ?? ''));
        $sort   = $filters['sort'] ?? null;
        $query  = PickingSession::query()
            ->leftJoin('users', 'users.id', '=', 'picking_sessions.user_id')
            ->select('picking_sessions.*')
            ->where('picking_sessions.warehouse_id', $warehouse->id)
            ->where(fn (Builder $query) => $query->whereNull('picking_sessions.type')->orWhere('picking_sessions.type', PickingSessionTypeEnum::DROPSHIPPING->value))
            ->when($state, fn (Builder $query) => $query->where('picking_sessions.state', $state))
            ->when($search !== '', fn (Builder $query) => $query->whereStartWith('picking_sessions.reference', $search))
            ->with('user');

        if ($sort) {
            $descending = str_starts_with($sort, '-');
            $query->orderByRaw(self::SORTS[ltrim($sort, '-')].($descending ? ' desc nulls last' : ' asc nulls last'))
                ->orderBy('picking_sessions.id', $descending ? 'desc' : 'asc');
        } else {
            $query->orderByRaw('(picking_sessions.state = ?) asc', [PickingSessionStateEnum::PACKING_FINISHED->value])
                ->orderByDesc('picking_sessions.id');
        }

        return $query->paginate(perPage: self::PER_PAGE, page: (int) ($filters['page'] ?? 1));
    }

    /**
     * @return array<string, int>
     */
    private function counts(Warehouse $warehouse): array
    {
        $stats = $warehouse->stats;

        return collect(PickingSessionStateEnum::cases())
            ->mapWithKeys(fn (PickingSessionStateEnum $state) => [$state->value => (int) ($stats?->{'number_picking_sessions_state_'.$state->snake()} ?? 0)])
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(self::canViewGoodsOut($user, $warehouse), 403);

        $filters = Validator::make($request->query(), [
            'state' => ['sometimes', 'nullable', Rule::in(PickingSessionStateEnum::values())],
            'q'     => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort'  => ['sometimes', 'nullable', Rule::in(collect(array_keys(self::SORTS))->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'page'  => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $pickingSessions = $this->handle($warehouse, $filters);

        return response()->json([
            'data'       => collect($pickingSessions->items())->map(fn (PickingSession $pickingSession) => $this->pickingSessionRow($pickingSession))->all(),
            'counts'     => $this->counts($warehouse),
            'pagination' => $this->paginationMeta($pickingSessions),
        ]);
    }
}
