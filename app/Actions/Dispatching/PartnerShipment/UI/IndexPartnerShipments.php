<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithOrganisationDispatchingViewAuthorisation;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\PartnerCustomsInvoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Trucks to partners (HELP-4482), the consolidated delivery notes, each with its customs invoices.
 * Customs invoices are customs-only documents: never invoices, never in sales or accounting.
 */
class IndexPartnerShipments extends OrgAction
{
    use WithOrganisationDispatchingViewAuthorisation;

    /**
     * A warehouse's consolidations, or every one of the organisation (or group); a shop shows its
     * organisation's, which is what customer services see in the orders backlog.
     */
    public function handle(Group|Organisation|Shop|Warehouse $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query
                ->whereStartWith('partner_shipments.reference', $value)
                ->orWhereHas('customsInvoices', fn ($query) => $query->whereStartWith('reference', $value))
                ->orWhereHas('deliveryNotes', fn ($query) => $query->whereStartWith('delivery_notes.reference', $value))
                ->orWhereRaw("jsonb_exists(partner_shipments.data->'invoices', ?)", [$value])
                ->orWhereRaw("jsonb_exists(partner_shipments.data->'delivery_notes', ?)", [$value]));
        });

        $query = PartnerShipment::query();
        match (true) {
            $parent instanceof Group     => $query->where('partner_shipments.group_id', $parent->id),
            $parent instanceof Warehouse => $query->where('partner_shipments.warehouse_id', $parent->id),
            default                  => $query->where('partner_shipments.organisation_id', $parent->organisation_id ?? $parent->id),
        };

        return QueryBuilder::for($query)
            ->with(['organisation', 'warehouse', 'orgPartner.partner', 'customsInvoices', 'voidedBy'])
            ->withCount('deliveryNotes')
            ->defaultSort('-id')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['id', 'reference', 'date'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (PartnerShipment $partnerShipment) => [
                'id'                    => $partnerShipment->id,
                'reference'             => $partnerShipment->reference,
                'route'                 => [
                    'name'       => 'grp.org.warehouses.show.dispatching.partner_shipments.show',
                    'parameters' => [$partnerShipment->organisation->slug, $partnerShipment->warehouse->slug, $partnerShipment->id],
                ],
                'partner'               => $partnerShipment->orgPartner->partner->code,
                'date'                  => $partnerShipment->date->toDateString(),
                'state'                 => self::stateLabel($partnerShipment),
                'is_void'               => $partnerShipment->voided_at !== null,
                'number_delivery_notes' => $partnerShipment->voided_at ? count($partnerShipment->data['delivery_notes'] ?? []) : $partnerShipment->delivery_notes_count,
                'number_pallets'        => $partnerShipment->number_pallets,
                'customs_invoices'      => $partnerShipment->customsInvoices->map(fn (PartnerCustomsInvoice $customsInvoice) => [
                    'reference'    => $customsInvoice->reference,
                    'label'        => $customsInvoice->label,
                    'download_url' => route('grp.org.warehouses.show.dispatching.customs_invoices.export', [$partnerShipment->organisation->slug, $partnerShipment->warehouse->slug, $customsInvoice->id]),
                ])->all(),
            ]);
    }

    public static function stateLabel(PartnerShipment $partnerShipment): string
    {
        return match (true) {
            $partnerShipment->voided_at !== null           => __('Void'),
            $partnerShipment->dispatched_at !== null       => __('Dispatched'),
            $partnerShipment->customs_invoiced_at !== null => __('Customs invoices ready'),
            default                                        => __('Open'),
        };
    }

    /**
     * The partners we send trucks to: the ones with a goods out location to gather their stock.
     *
     * @return Collection<int, OrgPartner>
     */
    public static function shippedToPartners(Organisation $organisation): Collection
    {
        return OrgPartner::where('organisation_id', $organisation->id)
            ->whereNotNull('goods_out_location_id')
            ->with('partner')
            ->orderBy('id')
            ->get();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('consolidated delivery note'), __('consolidated delivery notes')])
                ->withEmptyState([
                    'title'       => __('No consolidated delivery notes yet'),
                    'description' => __('Tick a partner\'s packed or finalised delivery notes in Delivery notes and press Consolidate, or use the buttons above'),
                ])
                ->column(key: 'reference', label: __('Consolidated'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'partner', label: __('Partner'), canBeHidden: false)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'number_delivery_notes', label: __('Delivery notes'), canBeHidden: false, align: 'right')
                ->column(key: 'number_pallets', label: __('Pallets'), canBeHidden: false, align: 'right')
                ->column(key: 'customs_invoices', label: __('Customs invoices'), canBeHidden: false)
                ->defaultSort('-id');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($warehouse->organisation_id === $organisation->id, 404);
        $this->warehouse = $warehouse;
        $this->initialisation($organisation, $request);

        return $this->handle($warehouse);
    }

    public function htmlResponse(LengthAwarePaginator $partnerShipments, ActionRequest $request): Response
    {
        $title     = __('Consolidated delivery notes');
        $warehouse = $this->warehouse;
        $canWork   = $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));

        return Inertia::render(
            'Org/Dispatching/PartnerShipments',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'    => ['icon' => ['fal', 'fa-layer-group'], 'title' => $title],
                    'title'   => $title,
                    'actions' => $canWork ? self::shippedToPartners($this->organisation)->map(fn (OrgPartner $orgPartner) => [
                        'type'  => 'button',
                        'style' => 'create',
                        'label' => __('Consolidate to :partner', ['partner' => $orgPartner->partner->code]),
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.partner_shipments.create',
                            'parameters' => [$this->organisation->slug, $warehouse->slug, $orgPartner->id],
                        ],
                    ])->values()->all() : [],
                ],
                'notice'      => __('One consolidated delivery note per shipment to a partner. Its customs invoices are customs-only documents: not invoices, the sales are on the shop invoices they list, and they never count in sales or accounting.'),
                'data'        => $partnerShipments,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $parameters = Arr::only($routeParameters, ['organisation', 'warehouse']);

        return array_merge(
            ShowWarehouse::make()->getBreadcrumbs($parameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.org.warehouses.show.dispatching.partner_shipments.index', 'parameters' => $parameters],
                        'label' => __('Consolidated delivery notes'),
                    ],
                ],
            ]
        );
    }
}
