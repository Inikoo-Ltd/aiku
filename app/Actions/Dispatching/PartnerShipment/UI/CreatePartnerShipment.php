<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment\UI;

use App\Actions\Dispatching\PartnerShipment\StorePartnerShipment;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Put a partner's packed or finalised delivery notes on a truck (HELP-4482); each shows its invoice,
 * or that it is waiting to be finalised.
 */
class CreatePartnerShipment extends OrgAction
{
    private OrgPartner $orgPartner;
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));
    }

    public function handle(Warehouse $warehouse, OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('delivery_notes.reference', $value)->orWhereRaw('inv.invoices ILIKE ?', [$value.'%']));
        });

        return self::withInvoicesAndParcels(QueryBuilder::for(StorePartnerShipment::eligibleDeliveryNotesQuery($orgPartner, $warehouse)))
            ->defaultSort('reference')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['reference', 'date', 'shop_code', 'state', 'effective_weight'])
            ->withPaginator(null, 100, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(fn (DeliveryNote $deliveryNote) => self::row($deliveryNote));
    }

    /**
     * Adds each note's invoice references and parcel count to a delivery notes query.
     */
    public static function withInvoicesAndParcels($query)
    {
        $invoices = DB::table('delivery_note_order as dno')
            ->join('invoices', 'invoices.order_id', '=', 'dno.order_id')
            ->whereColumn('dno.delivery_note_id', 'delivery_notes.id')
            ->where('invoices.type', 'invoice')
            ->where('invoices.in_process', false)
            ->whereNull('invoices.deleted_at')
            ->selectRaw("string_agg(DISTINCT invoices.reference, ', ') as invoices");

        return $query
            ->leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
            ->leftJoinLateral($invoices, 'inv')
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.slug',
                'delivery_notes.state',
                'delivery_notes.date',
                'delivery_notes.number_items',
                'delivery_notes.effective_weight',
                DB::raw("CASE WHEN jsonb_typeof(delivery_notes.parcels) = 'array' THEN jsonb_array_length(delivery_notes.parcels) ELSE 0 END as number_parcels"),
                'shops.code as shop_code',
                'inv.invoices',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(DeliveryNote $deliveryNote): array
    {
        return [
                'id'             => $deliveryNote->id,
                'reference'      => $deliveryNote->reference,
                'invoice'        => $deliveryNote->invoices ?: __('Not invoiced yet'),
                'is_invoiced'    => (bool) $deliveryNote->invoices,
                'shop_code'      => $deliveryNote->shop_code,
                'state'          => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value] ?? $deliveryNote->state->value,
                'is_dispatched'  => $deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED,
                'date'           => $deliveryNote->date?->toDateString(),
                'number_parcels' => (int) $deliveryNote->number_parcels,
                'number_items'   => (int) $deliveryNote->number_items,
                'weight'         => round((float) $deliveryNote->effective_weight / 1000, 1),
        ];
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('delivery note'), __('delivery notes')])
                ->withEmptyState([
                    'title'       => __('Nothing packed for this partner'),
                    'description' => __('Packed and finalised delivery notes not yet consolidated show here'),
                ])
                ->column(key: 'reference', label: __('Delivery note'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'invoice', label: __('Invoice'), canBeHidden: false)
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'number_parcels', label: __('Parcels'), canBeHidden: false, align: 'right')
                ->column(key: 'weight', label: __('Weight (kg)'), canBeHidden: false, align: 'right')
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id, 404);
        $this->orgPartner = $orgPartner;
        $this->warehouse  = $warehouse;
        $this->initialisation($organisation, $request);

        return $this->handle($warehouse, $orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        $title = __('New consolidated delivery note to :partner', ['partner' => $this->orgPartner->partner->code]);

        return Inertia::render(
            'Org/Dispatching/CreatePartnerShipment',
            [
                'breadcrumbs' => IndexPartnerShipments::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => ['icon' => ['fal', 'fa-layer-group'], 'title' => $title],
                    'model' => $this->orgPartner->partner->name,
                    'title' => $title,
                ],
                'defaults'    => [
                    'next_reference' => StorePartnerShipment::nextReference($this->orgPartner, SerialReferenceModelEnum::PARTNER_SHIPMENT),
                    'date'           => now()->toDateString(),
                ],
                'splitsNote'  => match (true) {
                    (bool) $this->orgPartner->split_cosmetics => __('Its customs invoices: one for cosmetics and one for the rest'),
                    (bool) $this->orgPartner->split_gb_origin => __('Its customs invoices: one for GB origin goods (preferential) and one for the rest'),
                    default                                   => null,
                },
                'storeRoute'  => [
                    'name'       => 'grp.org.warehouses.show.dispatching.partner_shipments.store',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'data'        => $deliveryNotes,
            ]
        )->table($this->tableStructure());
    }
}
