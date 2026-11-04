<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment\UI;

use App\Actions\Dispatching\PartnerShipment\GetPartnerShipmentCustomsLines;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Trucks of partner orders and their customs invoices (HELP-4482).
 */
class IndexPartnerShipments extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgPartnerSubNavigation;

    private OrgPartner $orgPartner;

    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereExists(fn ($inner) => $inner->selectRaw('1')
                ->from('partner_shipment_delivery_notes')
                ->join('delivery_notes', 'delivery_notes.id', '=', 'partner_shipment_delivery_notes.delivery_note_id')
                ->whereColumn('partner_shipment_delivery_notes.partner_shipment_id', 'partner_shipments.id')
                ->whereStartWith('delivery_notes.reference', $value));
        });

        return QueryBuilder::for(PartnerShipment::query()->where('org_partner_id', $orgPartner->id))
            ->withCount('deliveryNotes')
            ->defaultSort('-date')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['date', 'first_number', 'number_pallets'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(fn (PartnerShipment $partnerShipment) => [
                'id'                    => $partnerShipment->id,
                'date'                  => $partnerShipment->date->toDateString(),
                'number_pallets'        => $partnerShipment->number_pallets,
                'number_delivery_notes' => $partnerShipment->delivery_notes_count,
                'documents'             => collect($partnerShipment->splits)->values()->map(fn (string $split, int $index) => [
                    'reference' => $partnerShipment->documentReference($index),
                    'label'     => GetPartnerShipmentCustomsLines::splitLabel($orgPartner, $split),
                    'url'       => route('grp.org.procurement.org_partners.show.customs_invoices.export', [
                        $orgPartner->organisation->slug,
                        $orgPartner->id,
                        $partnerShipment->id,
                        $split,
                    ]),
                ])->all(),
            ]);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('shipment'), __('shipments')])
                ->withEmptyState([
                    'title'       => __('No customs invoices yet'),
                    'description' => __('Create one when a truck is ready: pick its packed delivery notes'),
                ])
                ->column(key: 'documents', label: __('Customs invoices'), canBeHidden: false)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'number_delivery_notes', label: __('Delivery notes'), canBeHidden: false, align: 'right')
                ->column(key: 'number_pallets', label: __('Pallets'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $partnerShipments, ActionRequest $request): Response
    {
        $title = __('Customs invoices');

        return Inertia::render(
            'Procurement/PartnerShipments',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => '('.$this->orgPartner->partner->code.') '.$title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice'],
                        'title' => $title,
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => $title,
                    'actions'       => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Customs invoice'),
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.show.customs_invoices.create',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ] : [],
                    'subNavigation' => $this->getOrgPartnerNavigation($this->orgPartner),
                ],
                'data'        => $partnerShipments,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowOrgPartner::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.customs_invoices.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Customs invoices'),
                    ],
                ],
            ]
        );
    }
}
