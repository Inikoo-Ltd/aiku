<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickingSession\UI\IndexPickingSessions;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function handle(Warehouse $warehouse, ?string $state, int $page): LengthAwarePaginator
    {
        return PickingSession::query()
            ->where('warehouse_id', $warehouse->id)
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', PickingSessionTypeEnum::DROPSHIPPING->value))
            ->when($state, fn ($query) => $query->where('state', $state))
            ->with('user')
            ->orderByRaw('(state = ?) asc', [PickingSessionStateEnum::PACKING_FINISHED->value])
            ->orderByDesc('id')
            ->paginate(perPage: self::PER_PAGE, page: $page);
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
        abort_unless($warehouse->group_id === $user->group_id && IndexPickingSessions::canViewDispatching($user, $warehouse), 403);

        $filters = Validator::make($request->query(), [
            'state' => ['sometimes', 'nullable', Rule::in(PickingSessionStateEnum::values())],
            'page'  => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $pickingSessions = $this->handle($warehouse, $filters['state'] ?? null, (int) ($filters['page'] ?? 1));

        return response()->json([
            'data'       => collect($pickingSessions->items())->map(fn (PickingSession $pickingSession) => $this->pickingSessionRow($pickingSession))->all(),
            'counts'     => $this->counts($warehouse),
            'pagination' => $this->paginationMeta($pickingSessions),
        ]);
    }
}
