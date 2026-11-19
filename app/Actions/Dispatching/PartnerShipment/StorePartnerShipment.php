<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Puts a partner's packed or finalised delivery notes on a truck, the consolidated delivery note
 * (HELP-4482). Its customs invoices are made later with StorePartnerCustomsInvoices, once every
 * note is invoiced.
 */
class StorePartnerShipment extends OrgAction
{
    /**
     * Notes of a partner that can go on a truck: packed or finalised, from any of our shops (each
     * shop has its own customer record for the partner), not on another truck. Dispatched ones only
     * for 30 days, so a truck can still be made up after it left.
     *
     * @return Builder<DeliveryNote>
     */
    public static function eligibleDeliveryNotesQuery(OrgPartner $orgPartner, ?Warehouse $warehouse = null): Builder
    {
        return DeliveryNote::query()
            ->where('delivery_notes.organisation_id', $orgPartner->organisation_id)
            ->when($warehouse, fn (Builder $query) => $query->where('delivery_notes.warehouse_id', $warehouse->id))
            ->whereIn(
                'delivery_notes.customer_id',
                Customer::query()->select('id')
                    ->where('organisation_id', $orgPartner->organisation_id)
                    ->where('as_organisation_id', $orgPartner->partner_id)
            )
            ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED])
            ->where(fn (Builder $query) => $query->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::DISPATCHED)
                ->orWhere('delivery_notes.dispatched_at', '>=', now()->subDays(30)))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('partner_shipment_delivery_notes')
                ->whereColumn('partner_shipment_delivery_notes.delivery_note_id', 'delivery_notes.id'));
    }

    public static function serialReference(OrgPartner $orgPartner, SerialReferenceModelEnum $model): void
    {
        $prefix = strtoupper(substr($orgPartner->organisation->code, 0, 3).$orgPartner->partner->code);
        $orgPartner->serialReferences()->firstOrCreate(
            ['model' => $model],
            [
                'organisation_id' => $orgPartner->organisation_id,
                'format'          => $model === SerialReferenceModelEnum::PARTNER_SHIPMENT ? $prefix.'-C%04d' : $prefix.'%04d',
            ]
        );
    }

    public static function nextReference(OrgPartner $orgPartner, SerialReferenceModelEnum $model = SerialReferenceModelEnum::CUSTOMS_INVOICE): string
    {
        $serialReference = $orgPartner->serialReferences()->where('model', $model)->first();
        if (!$serialReference) {
            $prefix = strtoupper(substr($orgPartner->organisation->code, 0, 3).$orgPartner->partner->code);

            return sprintf($model === SerialReferenceModelEnum::PARTNER_SHIPMENT ? $prefix.'-C%04d' : $prefix.'%04d', 1);
        }

        return sprintf($serialReference->format, $serialReference->serial + 1);
    }

    /**
     * @param array{delivery_note_ids: array<int, int>, date: string, number_pallets?: int|null} $modelData
     *
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, OrgPartner $orgPartner, array $modelData): PartnerShipment
    {
        return DB::transaction(function () use ($warehouse, $orgPartner, $modelData) {
            DB::select('select pg_advisory_xact_lock(4482, ?)', [$orgPartner->id]);

            $requestedIds = array_values(array_unique(array_map('intval', $modelData['delivery_note_ids'])));
            $eligibleIds  = self::eligibleDeliveryNotesQuery($orgPartner, $warehouse)
                ->whereIn('delivery_notes.id', $requestedIds)
                ->pluck('delivery_notes.id')
                ->all();

            if (count($eligibleIds) !== count($requestedIds)) {
                throw ValidationException::withMessages([
                    'delivery_note_ids' => __('Some delivery notes are not packed or finalised, are not for this partner or are already on another consolidation'),
                ]);
            }

            self::serialReference($orgPartner, SerialReferenceModelEnum::PARTNER_SHIPMENT);

            /** @var PartnerShipment $partnerShipment */
            $partnerShipment = PartnerShipment::create([
                'group_id'        => $orgPartner->group_id,
                'organisation_id' => $orgPartner->organisation_id,
                'org_partner_id'  => $orgPartner->id,
                'warehouse_id'    => $warehouse->id,
                'reference'       => GetSerialReference::run($orgPartner, SerialReferenceModelEnum::PARTNER_SHIPMENT),
                'date'            => $modelData['date'],
                'number_pallets'  => $modelData['number_pallets'] ?? null,
            ]);
            $partnerShipment->deliveryNotes()->attach($requestedIds);

            return $partnerShipment;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));
    }

    public function rules(): array
    {
        return [
            'delivery_note_ids'   => ['required', 'array', 'min:1'],
            'delivery_note_ids.*' => ['integer'],
            'date'                => ['required', 'date'],
            'number_pallets'      => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, OrgPartner $orgPartner, ActionRequest $request): PartnerShipment
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id, 404);
        $this->warehouse = $warehouse;
        $this->initialisation($organisation, $request);

        return $this->handle($warehouse, $orgPartner, $this->validatedData);
    }

    public function htmlResponse(PartnerShipment $partnerShipment): RedirectResponse
    {
        return redirect()->route('grp.org.warehouses.show.dispatching.partner_shipments.show', [
            $partnerShipment->organisation->slug,
            $this->warehouse->slug,
            $partnerShipment->id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Warehouse $warehouse, OrgPartner $orgPartner, array $modelData): PartnerShipment
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, $modelData);

        return $this->handle($warehouse, $orgPartner, $this->validatedData);
    }
}
