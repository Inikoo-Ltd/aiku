<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\GetMobileWarehouses;
use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileScan
{
    use AsController;
    use WithMobileDeliveryNotes;

    /**
     * @return array{code: string, type: string, sko: array<string, mixed>|null, location: array<string, mixed>|null, delivery_note: array<string, mixed>|null}
     */
    public function handle(Warehouse $warehouse, User $user, string $code): array
    {
        $code   = trim($code);
        $can    = app(GetMobileWarehouses::class)->abilities($user, $warehouse);
        $result = [
            'code'          => $code,
            'type'          => 'unknown',
            'sko'           => null,
            'location'      => null,
            'delivery_note' => null,
        ];

        if ($can['view_inventory'] && $orgStock = ScanSkoBarcode::make()->handle($warehouse->organisation, $code)) {
            return array_merge($result, ['type' => 'sko', 'sko' => $this->sko($warehouse, $orgStock, $code)]);
        }

        if ($can['view_locations'] && $location = $this->scannedLocation($warehouse, $code)) {
            return array_merge($result, ['type' => 'location', 'location' => $this->location($location)]);
        }

        if ($can['view_goods_out'] && $deliveryNote = $this->scannedDeliveryNote($warehouse, $user, $code)) {
            return array_merge($result, ['type' => 'delivery_note', 'delivery_note' => $this->deliveryNoteRow($deliveryNote)]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function sko(Warehouse $warehouse, OrgStock $orgStock, string $code): array
    {
        $card = ScanSkoBarcode::card($warehouse, $orgStock);

        return [
            'id'                    => $card['id'],
            'code'                  => $card['code'],
            'name'                  => $card['name'],
            'state'                 => $orgStock->state->value,
            'barcode'               => $card['barcode'],
            'unit_barcode'          => $card['unit_barcode'],
            'packed_in'             => (int) $card['packed_in'],
            'quantity_in_locations' => (float) $card['quantity_in_locations'],
            'image_url'             => Arr::get($card['image'] ?? [], 'original'),
            'locations'             => collect($card['locations'])->map(fn (array $location) => [
                'code'     => $location['code'],
                'quantity' => (float) $location['quantity'],
            ])->all(),
            'matched_on'            => ScanSkoBarcode::matchedOn($orgStock, $code),
        ];
    }

    private function scannedLocation(Warehouse $warehouse, string $code): ?Location
    {
        /** @var Location|null $location */
        $location = Location::where('warehouse_id', $warehouse->id)
            ->whereRaw('lower(code) = lower(?)', [$code])
            ->orderBy('id')
            ->first();

        return $location;
    }

    /**
     * @return array<string, mixed>
     */
    private function location(Location $location): array
    {
        $location->loadMissing(['stats', 'warehouseArea']);
        $area = $location->warehouseArea;

        return [
            'id'                => $location->id,
            'code'              => $location->code,
            'slug'              => $location->slug,
            'warehouse_area'    => $area ? [
                'id'   => $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ] : null,
            'number_org_stocks' => (int) ($location->stats?->number_org_stock_slots ?? 0),
        ];
    }

    private function scannedDeliveryNote(Warehouse $warehouse, User $user, string $code): ?DeliveryNote
    {
        $listed = fn () => $this->withRowColumns($this->deliveryNotesQuery($warehouse, 'all', 'all', null, $user));

        /** @var DeliveryNote|null $deliveryNote */
        $deliveryNote = $listed()->whereRaw('lower(delivery_notes.reference) = lower(?)', [$code])->first()
            ?? $listed()->whereRaw("lower(?) = any(regexp_split_to_array(lower(delivery_notes.tracking_number), '\\s*,\\s*'))", [$code])->first();

        return $deliveryNote;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $can  = app(GetMobileWarehouses::class)->abilities($user, $warehouse);
        abort_unless($warehouse->group_id === $user->group_id && ($can['view_goods_out'] || $can['view_inventory'] || $can['view_locations']), 403);

        $validated = Validator::make($request->query(), [
            'code' => ['required', 'string', 'max:255'],
        ])->validate();

        return response()->json(['data' => $this->handle($warehouse, $user, $validated['code'])]);
    }
}
