<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment\UI;

use App\Actions\Dispatching\PartnerShipment\StorePartnerCustomsInvoices;
use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Dispatching\PartnerCustomsInvoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * A truck to a partner, the consolidated delivery note (HELP-4482): its notes with their invoices,
 * its customs invoices, and the make, dispatch and void buttons.
 */
class ShowPartnerShipment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return PartnerShipment::canBeSeenBy($request->user(), $this->organisation);
    }

    /**
     * The notes on the truck, as the page table and the consolidated delivery note list them.
     *
     * @return Builder<DeliveryNote>
     */
    public static function notesQuery(PartnerShipment $partnerShipment): Builder
    {
        return CreatePartnerShipment::withInvoicesAndParcels(
            DeliveryNote::query()->whereIn('delivery_notes.id', $partnerShipment->deliveryNotes()->select('delivery_notes.id'))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function noteRows(PartnerShipment $partnerShipment): array
    {
        return self::notesQuery($partnerShipment)->orderBy('delivery_notes.reference')->get()
            ->map(fn (DeliveryNote $deliveryNote) => CreatePartnerShipment::row($deliveryNote))
            ->all();
    }

    public function notesTable(PartnerShipment $partnerShipment): LengthAwarePaginator
    {
        return QueryBuilder::for(self::notesQuery($partnerShipment))
            ->defaultSort('reference')
            ->allowedSorts(['reference', 'date', 'shop_code', 'state'])
            ->withPaginator(null, 100, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(fn (DeliveryNote $deliveryNote) => CreatePartnerShipment::row($deliveryNote));
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('delivery note'), __('delivery notes')])
                ->withEmptyState(['title' => __('No delivery notes on this consolidation')])
                ->column(key: 'reference', label: __('Delivery note'), canBeHidden: false, sortable: true)
                ->column(key: 'invoice', label: __('Invoice'), canBeHidden: false)
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'number_parcels', label: __('Parcels'), canBeHidden: false, align: 'right')
                ->column(key: 'number_items', label: __('Items'), canBeHidden: false, align: 'right')
                ->column(key: 'weight', label: __('Weight (kg)'), canBeHidden: false, align: 'right')
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerShipment $partnerShipment, ActionRequest $request): PartnerShipment
    {
        abort_unless($partnerShipment->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->warehouse = $warehouse;
        $this->initialisation($organisation, $request);

        return $partnerShipment;
    }

    public function htmlResponse(PartnerShipment $partnerShipment, ActionRequest $request): Response
    {
        $organisation = $this->organisation;
        $warehouse    = $this->warehouse;
        $parameters   = [$organisation->slug, $warehouse->slug, $partnerShipment->id];
        $canWork      = $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($organisation));
        $isVoid       = $partnerShipment->voided_at !== null;
        $notes        = $isVoid ? [] : self::noteRows($partnerShipment);
        $notesTable   = $this->notesTable($partnerShipment);
        $waiting      = $isVoid || $partnerShipment->customs_invoiced_at ? [] : StorePartnerCustomsInvoices::waitingForInvoices($partnerShipment);
        $partner      = $partnerShipment->orgPartner->partner;

        return Inertia::render(
            'Org/Dispatching/PartnerShipment',
            [
                'title'       => $partnerShipment->reference,
                'breadcrumbs' => array_merge(
                    IndexPartnerShipments::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'route' => ['name' => 'grp.org.warehouses.show.dispatching.partner_shipments.show', 'parameters' => $parameters],
                                'label' => $partnerShipment->reference,
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => ['icon' => ['fal', 'fa-layer-group'], 'title' => __('Consolidated delivery note')],
                    'model' => __('Consolidated delivery note to :partner', ['partner' => $partner->name]),
                    'title' => $partnerShipment->reference,
                ],
                'truck'       => [
                    'reference'      => $partnerShipment->reference,
                    'partner'        => $partner->code,
                    'date'           => $partnerShipment->date->toDateString(),
                    'number_pallets' => $partnerShipment->number_pallets,
                    'state'          => match (true) {
                        $isVoid                                  => 'void',
                        $partnerShipment->dispatched_at !== null => 'dispatched',
                        $partnerShipment->customs_invoiced_at !== null => 'customs_ready',
                        default                                  => 'loading',
                    },
                    'void_note'      => $isVoid ? __('Void on :date by :user: :reason', [
                        'date'   => $partnerShipment->voided_at->toDateString(),
                        'user'   => $partnerShipment->voidedBy?->contact_name ?? $partnerShipment->voidedBy?->username ?? '-',
                        'reason' => $partnerShipment->void_reason,
                    ]) : null,
                    'dispatched_at'  => $partnerShipment->dispatched_at?->toDateTimeString(),
                    'weight'         => round(collect($notes)->sum('weight'), 1),
                    'number_parcels' => collect($notes)->sum('number_parcels'),
                ],
                'data'            => $notesTable,
                'number_notes'    => count($notes),
                'waiting'         => $waiting,
                'customsInvoices' => $partnerShipment->customsInvoices->map(fn (PartnerCustomsInvoice $customsInvoice) => [
                    'reference'    => $customsInvoice->reference,
                    'label'        => $customsInvoice->label,
                    'lines'        => $customsInvoice->number_lines,
                    'units'        => (float) $customsInvoice->units,
                    'amount'       => (float) $customsInvoice->amount,
                    'currency'     => $partnerShipment->data['currency'] ?? null,
                    'gross_weight' => (float) $customsInvoice->gross_weight,
                    'download_url' => route('grp.org.warehouses.show.dispatching.customs_invoices.export', [$organisation->slug, $warehouse->slug, $customsInvoice->id]),
                ])->all(),
                'packingListUrl'  => route('grp.org.warehouses.show.dispatching.partner_shipments.packing_list', $parameters),
                'canWork'         => $canWork,
                'canVoid'         => $request->user()->authTo(["org-admin.$organisation->id", "supervisor-dispatching.$warehouse->id"]),
                'routes'          => [
                    'customs'  => ['name' => 'grp.org.warehouses.show.dispatching.partner_shipments.customs', 'parameters' => $parameters],
                    'dispatch' => ['name' => 'grp.org.warehouses.show.dispatching.partner_shipments.dispatch', 'parameters' => $parameters],
                    'void'     => ['name' => 'grp.org.warehouses.show.dispatching.partner_shipments.void', 'parameters' => $parameters],
                ],
            ]
        )->table($this->tableStructure());
    }
}
