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
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDeliveryNotesForPickingSession extends OrgAction
{
    use AsAction;

    public function handle(PickingSession $pickingSession, string $mode, ?string $shopSlug, ?string $fulfilmentSlug, ?string $search): array
    {
        $shopId = null;

        if ($shopSlug) {
            $shopId = Shop::where('slug', $shopSlug)->value('id');
        } elseif ($fulfilmentSlug) {
            $shopId = Shop::where('slug', $fulfilmentSlug)->value('id');
        }

        if ($mode === 'remove') {
            $query = $pickingSession->deliveryNotes();

            if ($shopId) {
                $query->where('delivery_notes.shop_id', $shopId);
            }

            if ($search) {
                $isValidDate = (bool) strtotime($search) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $search);
                $query->where(function ($q) use ($search, $isValidDate) {
                    $q->where('delivery_notes.reference', 'ilike', "%{$search}%");
                    if ($isValidDate) {
                        $q->orWhereDate('delivery_notes.date', $search);
                    }
                });
            }

            return $query->get()
                ->map(fn (DeliveryNote $dn) => [
                    'id'          => $dn->id,
                    'created_at'  => $dn->created_at,
                    'reference'   => $dn->reference,
                    'state_label' => $dn->state->labels()[$dn->state->value] ?? 'N/A',
                    'number_items' => $dn->number_items,
                ])
                ->values()
                ->all();
        }

        $query = DeliveryNote::where('delivery_notes.warehouse_id', $pickingSession->warehouse_id)
            ->whereIn('delivery_notes.state', [
                DeliveryNoteStateEnum::UNASSIGNED->value,
                DeliveryNoteStateEnum::QUEUED->value,
            ])
            ->whereDoesntHave('pickingSessions');

        if ($shopId) {
            $query->where('delivery_notes.shop_id', $shopId);
        }

        if ($search) {
            $isValidDate = (bool) strtotime($search) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $search);
            $query->where(function ($q) use ($search, $isValidDate) {
                $q->where('delivery_notes.reference', 'ilike', "%{$search}%");
                if ($isValidDate) {
                    $q->orWhereDate('delivery_notes.date', $search);
                }
            });
        }

        return $query->get()
            ->map(fn (DeliveryNote $dn) => [
                'id'          => $dn->id,
                'created_at'  => $dn->created_at,
                'reference'   => $dn->reference,
                'state_label' => $dn->state->labels()[$dn->state->value] ?? 'N/A',
                'number_items' => $dn->number_items,
            ])
            ->values()
            ->all();
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        $mode          = $request->query('mode', 'add');
        $shopSlug      = $request->query('shop') ?: null;
        $fulfilmentSlug = $request->query('fulfilment') ?: null;
        $search        = $request->query('search') ?: null;

        return response()->json($this->handle($pickingSession, $mode, $shopSlug, $fulfilmentSlug, $search));
    }
}
