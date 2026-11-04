<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment\UI;

use App\Actions\Dispatching\PartnerShipment\StorePartnerShipment;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
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
 * Pick the packed delivery notes going on a truck to the partner; the ones not dispatched yet start ticked.
 */
class CreatePartnerShipment extends OrgAction
{
    use WithOrgPartnerSubNavigation;

    private OrgPartner $orgPartner;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('delivery_notes.reference', $value);
        });

        return QueryBuilder::for(StorePartnerShipment::eligibleDeliveryNotesQuery($orgPartner))
            ->leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
            ->select([
                'delivery_notes.id',
                'delivery_notes.slug',
                'delivery_notes.reference',
                'delivery_notes.state',
                'delivery_notes.date',
                'delivery_notes.dispatched_at',
                'delivery_notes.effective_weight',
                'shops.code as shop_code',
            ])
            ->defaultSort('reference')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['reference', 'date', 'shop_code', 'state', 'effective_weight'])
            ->withPaginator(null, 100, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(fn (DeliveryNote $deliveryNote) => [
                'id'            => $deliveryNote->id,
                'reference'     => $deliveryNote->reference,
                'shop_code'     => $deliveryNote->shop_code,
                'state'         => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value] ?? $deliveryNote->state->value,
                'is_dispatched' => $deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED,
                'date'          => $deliveryNote->date?->toDateString(),
                'weight'        => round((float) $deliveryNote->effective_weight / 1000, 1),
            ]);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('delivery note'), __('delivery notes')])
                ->withEmptyState([
                    'title'       => __('Nothing packed for this partner'),
                    'description' => __('Packed delivery notes not yet on a customs invoice show here'),
                ])
                ->column(key: 'reference', label: __('Delivery note'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'weight', label: __('Weight (kg)'), canBeHidden: false, align: 'right')
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        $title = __('New customs invoice');

        return Inertia::render(
            'Procurement/CreatePartnerShipment',
            [
                'breadcrumbs'  => IndexPartnerShipments::make()->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'        => '('.$this->orgPartner->partner->code.') '.$title,
                'pageHead'     => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file-invoice'],
                        'title' => $title,
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => $title,
                    'subNavigation' => $this->getOrgPartnerNavigation($this->orgPartner),
                ],
                'defaults'     => [
                    ...StorePartnerShipment::nextNumber($this->orgPartner),
                    'date' => now()->toDateString(),
                ],
                'splitsNote'   => match (true) {
                    (bool) $this->orgPartner->split_cosmetics => __('One invoice for cosmetics and one for the rest'),
                    (bool) $this->orgPartner->split_gb_origin => __('One invoice for GB origin goods (preferential) and one for the rest'),
                    default                                   => null,
                },
                'storeRoute'   => [
                    'name'       => 'grp.org.procurement.org_partners.show.customs_invoices.store',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'data'         => $deliveryNotes,
            ]
        )->table($this->tableStructure());
    }
}
