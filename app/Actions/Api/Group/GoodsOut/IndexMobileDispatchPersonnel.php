<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileDispatchPersonnel
{
    use AsController;

    /**
     * @return array<int, array{user: array{id: int, name: string|null}, delivery_notes: array<int, array{id: int, reference: string}>, trolleys: array<int, array{id: int, name: string}>, picking_sessions: array<int, array{id: int, reference: string}>}>
     */
    public function handle(Warehouse $warehouse, string $role): array
    {
        $userColumn    = ShowDispatchHub::CURRENT_WORK[$role]['user_column'];
        $deliveryNotes = ShowDispatchHub::currentWorkDeliveryNotes($warehouse, $role)
            ->orderBy('delivery_notes.reference')
            ->get(['delivery_notes.id', 'delivery_notes.reference', "delivery_notes.$userColumn as user_id"]);

        $deliveryNoteIds = $deliveryNotes->pluck('id')->all();

        $sessionsByDeliveryNote = DB::table('picking_session_has_delivery_notes')
            ->join('picking_sessions', 'picking_sessions.id', '=', 'picking_session_has_delivery_notes.picking_session_id')
            ->whereIn('picking_session_has_delivery_notes.delivery_note_id', $deliveryNoteIds)
            ->orderBy('picking_sessions.reference')
            ->get(['picking_session_has_delivery_notes.delivery_note_id', 'picking_sessions.id', 'picking_sessions.reference'])
            ->groupBy('delivery_note_id');

        $trolleys = Trolley::query()
            ->where(fn ($query) => $query
                ->whereIn('current_delivery_note_id', $deliveryNoteIds)
                ->orWhereIn('picking_session_id', $sessionsByDeliveryNote->flatten(1)->pluck('id')->unique()->values()->all()))
            ->orderBy('name')
            ->get(['id', 'name', 'current_delivery_note_id', 'picking_session_id']);

        $users = User::whereIn('id', $deliveryNotes->pluck('user_id')->unique()->values()->all())->get()->keyBy('id');

        return $deliveryNotes
            ->groupBy('user_id')
            ->map(function (Collection $userDeliveryNotes, int $userId) use ($sessionsByDeliveryNote, $trolleys, $users) {
                $pickingSessions = $userDeliveryNotes
                    ->flatMap(fn (object $deliveryNote) => $sessionsByDeliveryNote->get($deliveryNote->id, collect()))
                    ->unique('id')
                    ->sortBy('reference')
                    ->values();
                $deliveryNoteIds  = $userDeliveryNotes->pluck('id')->all();
                $pickingSessionIds = $pickingSessions->pluck('id')->all();
                $user             = $users->get($userId);

                return [
                    'user'             => ['id' => $userId, 'name' => $user?->contact_name ?? $user?->username],
                    'delivery_notes'   => $userDeliveryNotes->map(fn (object $deliveryNote) => ['id' => (int) $deliveryNote->id, 'reference' => $deliveryNote->reference])->values()->all(),
                    'trolleys'         => $trolleys
                        ->filter(fn (Trolley $trolley) => in_array($trolley->current_delivery_note_id, $deliveryNoteIds) || in_array($trolley->picking_session_id, $pickingSessionIds))
                        ->map(fn (Trolley $trolley) => ['id' => $trolley->id, 'name' => $trolley->name])
                        ->values()
                        ->all(),
                    'picking_sessions' => $pickingSessions->map(fn (object $pickingSession) => ['id' => (int) $pickingSession->id, 'reference' => $pickingSession->reference])->all(),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['user']['name']))
            ->values()
            ->all();
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($warehouse->group_id === $user->group_id && IndexDeliveryNotes::canViewDispatching($user, $warehouse), 403);

        $data = Validator::make($request->query(), [
            'role' => ['required', Rule::in(array_keys(ShowDispatchHub::CURRENT_WORK))],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $data['role'])]);
    }
}
