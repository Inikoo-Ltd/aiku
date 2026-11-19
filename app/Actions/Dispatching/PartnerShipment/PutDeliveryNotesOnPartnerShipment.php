<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Ticked partner delivery notes from the Packed or Finalised list go on a truck (HELP-4482): a new
 * one, or a truck of the same partner still loading (no customs invoices yet, not dispatched, not
 * void).
 */
class PutDeliveryNotesOnPartnerShipment extends OrgAction
{
    /**
     * Trucks still loading: notes can still be added to them.
     *
     * @return \Illuminate\Database\Eloquent\Builder<PartnerShipment>
     */
    public static function loadingTrucksQuery(Warehouse $warehouse)
    {
        return PartnerShipment::where('warehouse_id', $warehouse->id)
            ->whereNull('customs_invoiced_at')
            ->whereNull('dispatched_at')
            ->whereNull('voided_at');
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     *
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, array $deliveryNoteIds, ?int $partnerShipmentId): PartnerShipment
    {
        $partnerIds = DB::table('delivery_notes')
            ->join('customers', 'customers.id', '=', 'delivery_notes.customer_id')
            ->whereIn('delivery_notes.id', $deliveryNoteIds)
            ->distinct()
            ->pluck('customers.as_organisation_id');

        if ($partnerIds->count() !== 1 || $partnerIds->first() === null) {
            throw ValidationException::withMessages(['delivery_note_ids' => __('Tick notes of one partner only: a consolidated delivery note goes to one partner')]);
        }

        $orgPartner = OrgPartner::where('organisation_id', $warehouse->organisation_id)->where('partner_id', $partnerIds->first())->firstOrFail();

        if ($partnerShipmentId === null) {
            return StorePartnerShipment::make()->action($warehouse, $orgPartner, [
                'delivery_note_ids' => $deliveryNoteIds,
                'date'              => now()->toDateString(),
            ]);
        }

        return DB::transaction(function () use ($warehouse, $orgPartner, $deliveryNoteIds, $partnerShipmentId) {
            DB::select('select pg_advisory_xact_lock(4482, ?)', [$orgPartner->id]);
            $partnerShipment = self::loadingTrucksQuery($warehouse)->lockForUpdate()->find($partnerShipmentId);

            if (!$partnerShipment || $partnerShipment->org_partner_id !== $orgPartner->id) {
                throw ValidationException::withMessages(['partner_shipment_id' => __('That consolidation is for another partner, or it is no longer open')]);
            }

            $eligibleIds = StorePartnerShipment::eligibleDeliveryNotesQuery($orgPartner, $warehouse)
                ->whereIn('delivery_notes.id', $deliveryNoteIds)
                ->pluck('delivery_notes.id')
                ->all();
            if (count($eligibleIds) !== count(array_unique($deliveryNoteIds))) {
                throw ValidationException::withMessages([
                    'delivery_note_ids' => __('Some delivery notes are not packed or finalised, are not for this partner or are already on another consolidation'),
                ]);
            }

            $partnerShipment->deliveryNotes()->attach($eligibleIds);

            return $partnerShipment;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));
    }

    public function rules(): array
    {
        return [
            'delivery_note_ids'   => ['required', 'array', 'min:1'],
            'delivery_note_ids.*' => ['integer', 'exists:delivery_notes,id'],
            'partner_shipment_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): RedirectResponse
    {
        abort_unless($warehouse->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        $partnerShipment = $this->handle(
            $warehouse,
            array_values(array_unique(array_map('intval', $this->validatedData['delivery_note_ids']))),
            $this->validatedData['partner_shipment_id'] ?? null
        );

        return redirect()->route('grp.org.warehouses.show.dispatching.partner_shipments.show', [$organisation->slug, $warehouse->slug, $partnerShipment->id]);
    }

    /**
     * What the Packed and Finalised partner lists offer: a new truck, or a truck still loading.
     *
     * @return array<string, mixed>|null
     */
    public static function listOptions(Warehouse $warehouse, string $bucket, string $shopType, bool $canWork, array $deliveryNoteIds = []): ?array
    {
        if (!$canWork || $shopType !== 'partners' || !in_array($bucket, ['packed', 'finalised'], true)) {
            return null;
        }

        return [
            'notes'  => DB::table('delivery_notes')
                ->join('customers', 'customers.id', '=', 'delivery_notes.customer_id')
                ->leftJoin('organisations as partner', 'partner.id', '=', 'customers.as_organisation_id')
                ->leftJoin('partner_shipment_delivery_notes as psdn', 'psdn.delivery_note_id', '=', 'delivery_notes.id')
                ->leftJoin('partner_shipments as truck', 'truck.id', '=', 'psdn.partner_shipment_id')
                ->when(
                    $deliveryNoteIds,
                    fn ($query) => $query->whereIn('delivery_notes.id', $deliveryNoteIds),
                    fn ($query) => $query->where('delivery_notes.warehouse_id', $warehouse->id)
                        ->whereNotNull('customers.as_organisation_id')
                        ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::PACKED->value, DeliveryNoteStateEnum::FINALISED->value])
                )
                ->get(['delivery_notes.id', 'delivery_notes.reference', 'partner.code as partner', 'truck.reference as truck', 'truck.id as truck_id'])
                ->keyBy('id')
                ->map(fn ($row) => [
                    'reference' => $row->reference,
                    'partner'   => $row->partner,
                    'truck'     => $row->truck,
                    'truck_url' => $row->truck_id ? route('grp.org.warehouses.show.dispatching.partner_shipments.show', [$warehouse->organisation->slug, $warehouse->slug, $row->truck_id]) : null,
                ])
                ->all(),
            'route'  => [
                'name'       => 'grp.org.warehouses.show.dispatching.partner_shipments.put_on',
                'parameters' => [$warehouse->organisation->slug, $warehouse->slug],
            ],
            'trucks' => self::loadingTrucksQuery($warehouse)
                ->with('orgPartner.partner')
                ->withCount('deliveryNotes')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PartnerShipment $partnerShipment) => [
                    'id'      => $partnerShipment->id,
                    'partner' => $partnerShipment->orgPartner->partner->code,
                    'label'   => $partnerShipment->reference.' · '.$partnerShipment->orgPartner->partner->code.' · '.trans_choice(':count delivery note|:count delivery notes', $partnerShipment->delivery_notes_count),
                ])
                ->all(),
        ];
    }
}
