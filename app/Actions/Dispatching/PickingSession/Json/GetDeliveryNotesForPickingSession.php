<?php

/*
 * Author: Vika Aqordi
 * Created on: 2026-04-28
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

namespace App\Actions\Dispatching\PickingSession\Json;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDeliveryNotesForPickingSession extends OrgAction
{
    use AsAction;

    public function handle(PickingSession $pickingSession, string $mode): array
    {
        if ($mode === 'remove') {
            return $pickingSession->deliveryNotes()
                ->get()
                ->map(fn (DeliveryNote $dn) => [
                    'id'                    => $dn->id,
                    'created_at'            => $dn->created_at,
                    'reference'             => $dn->reference,
                    // 'customer_name' => $dn->company_name ?: $dn->contact_name ?: 'N/A',
                    'state_label'           => $dn->state->labels()[$dn->state->value] ?? 'N/A',
                    'number_items'          => $dn->number_items,
                ])
                ->values()
                ->all();
        }

        return DeliveryNote::where('warehouse_id', $pickingSession->warehouse_id)
            ->whereIn('state', [
                DeliveryNoteStateEnum::UNASSIGNED->value,
                DeliveryNoteStateEnum::QUEUED->value,
            ])
            ->whereDoesntHave('pickingSessions')
            ->get()
            ->map(fn (DeliveryNote $dn) => [
                'id'                    => $dn->id,
                'created_at'            => $dn->created_at,
                'reference'             => $dn->reference,
                // 'customer_name' => $dn->company_name ?: $dn->contact_name ?: 'N/A',
                'state_label'           => $dn->state->labels()[$dn->state->value] ?? 'N/A',
                'number_items'          => $dn->number_items,
            ])
            ->values()
            ->all();
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        $mode = $request->query('mode', 'add');

        return response()->json($this->handle($pickingSession, $mode));
    }
}
