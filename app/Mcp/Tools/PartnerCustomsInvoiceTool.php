<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Dispatching\PartnerShipment\GetPartnerShipmentCustomsLines;
use App\Actions\Dispatching\PartnerShipment\StorePartnerCustomsInvoices;
use App\Actions\Dispatching\PartnerShipment\StorePartnerShipment;
use App\Actions\Dispatching\PartnerShipment\UI\CreatePartnerShipment;
use App\Actions\Dispatching\PartnerShipment\UI\IndexPartnerShipments;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerCustomsInvoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Trucks to partners (HELP-4482) from an assistant, with the same actions as the Dispatching pages:
 * put packed or finalised delivery notes on a truck (the consolidated delivery note), and make its
 * customs invoices once every note is invoiced. Customs invoices are customs-only documents.
 */
#[Description('Consolidated delivery notes to partners (e.g. Aromatics to ES or SK): one per shipment, and its customs invoices are customs-only documents, never invoices, never in sales or accounting. Without create it shows the partner\'s packed and finalised delivery notes not yet consolidated, each with its invoice or "not invoiced yet" (proposed = from the last 30 days and not dispatched), the next consolidated delivery note number, the last ones with their customs invoices and, for the delivery notes given (or the proposed ones), the customs invoices they would make per split with lines, units, value and weight, and the products missing a tariff code or origin. With create=true, for users enrolled for procurement who can work in dispatching, it consolidates the delivery notes listed in a new consolidated delivery note and, when every one is invoiced, makes its customs invoices (numbers are given automatically and used once issued); otherwise it says which notes wait to be finalised. A delivery note and an invoice go in one consolidation only. It cannot be undone from the AI changes log. Only create after showing the preview and the user confirmed the delivery notes, the date and the pallets in their own words, passing their request text.')]
class PartnerCustomsInvoiceTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'     => ['required', 'string'],
            'partner'          => ['required', 'string'],
            'delivery_notes'   => ['required_if:create,true', 'array', 'min:1'],
            'delivery_notes.*' => ['string'],
            'date'             => ['sometimes', 'nullable', 'date'],
            'number_pallets'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'create'           => ['sometimes', 'boolean'],
            'request_text'     => ['required_if:create,true', 'string', 'max:4000'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$request->user()?->authTo(WarehousePermissionsEnum::getDispatchingViewPermissionNames($organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $partnerCode = strtolower((string) $request->string('partner'));
        $orgPartner  = OrgPartner::where('organisation_id', $organisation->id)
            ->whereHas('partner', fn ($query) => $query->whereRaw('(lower(code) = ? or lower(slug) = ?)', [$partnerCode, $partnerCode]))
            ->with('partner')
            ->first();
        if (!$orgPartner) {
            return Response::error("{$organisation->code} has no partner {$request->string('partner')}.");
        }

        $eligible = CreatePartnerShipment::withInvoicesAndParcels(StorePartnerShipment::eligibleDeliveryNotesQuery($orgPartner))
            ->addSelect('delivery_notes.warehouse_id')
            ->orderBy('delivery_notes.reference')
            ->get();

        $references = collect($request->get('delivery_notes', []))->map(fn ($reference) => strtolower(trim((string) $reference)));
        $selected   = $references->isEmpty()
            ? $eligible->filter(fn (DeliveryNote $deliveryNote) => $this->isProposed($deliveryNote))
            : $eligible->filter(fn (DeliveryNote $deliveryNote) => $references->contains(strtolower($deliveryNote->reference)));
        $unknown    = $references->diff($selected->map(fn (DeliveryNote $deliveryNote) => strtolower($deliveryNote->reference)))->values();

        if (!$request->boolean('create')) {
            return Response::json($this->preview($orgPartner, $eligible, $selected, $unknown->all()));
        }

        if (!$request->user()?->can_use_mcp_procurement) {
            return Response::error('Consolidating and making customs invoices is not enabled for this user. Do not retry; an administrator enrols it (procurement) on the user\'s edit page. Nothing was changed.');
        }
        if (!$request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($organisation))) {
            return Response::error("This user cannot work in dispatching in {$organisation->code}. Nothing was changed.");
        }
        if ($unknown->isNotEmpty()) {
            return Response::error('Not packed or finalised, not for this partner or already consolidated: '.$unknown->implode(', ').'. Nothing was changed.');
        }
        if ($selected->pluck('warehouse_id')->unique()->count() !== 1) {
            return Response::error('These delivery notes are in different warehouses; a consolidation leaves from one. Nothing was changed.');
        }

        $warehouse = Warehouse::findOrFail($selected->first()->warehouse_id);

        try {
            /** @var PartnerShipment $partnerShipment */
            $partnerShipment = $this->recordChange(
                $request,
                McpChangeTypeEnum::CUSTOMS_INVOICE,
                "Consolidated delivery note of {$organisation->code} to {$orgPartner->partner->code}",
                ['org_partner_id' => $orgPartner->id],
                fn () => StorePartnerShipment::make()->action($warehouse, $orgPartner, [
                    'delivery_note_ids' => $selected->pluck('id')->all(),
                    'date'              => $request->get('date') ?: now()->toDateString(),
                    'number_pallets'    => $request->get('number_pallets'),
                ]),
                ['organisation' => $organisation->code]
            );
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->implode(' ').' Nothing was changed.');
        }

        $customsError = null;
        if (!StorePartnerCustomsInvoices::waitingForInvoices($partnerShipment)) {
            try {
                StorePartnerCustomsInvoices::make()->handle($partnerShipment);
            } catch (ValidationException $exception) {
                $customsError = collect($exception->errors())->flatten()->implode(' ').' The consolidated delivery note was made; fix this and press Make customs invoices on its page.';
            }
        }
        $partnerShipment->refresh();

        return Response::json([
            'changed'              => true,
            'change_log_id'        => $this->mcpChange?->id,
            'consolidated'         => $partnerShipment->reference,
            'state'                => IndexPartnerShipments::stateLabel($partnerShipment),
            'delivery_notes'       => $selected->count(),
            'waiting_for_invoices' => StorePartnerCustomsInvoices::waitingForInvoices($partnerShipment),
            'customs_error'        => $customsError,
            'customs_invoices'     => $this->documents($partnerShipment),
            'page'           => route('grp.org.warehouses.show.dispatching.partner_shipments.show', [$organisation->slug, $warehouse->slug, $partnerShipment->id]),
        ]);
    }

    private function isProposed(DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->state !== DeliveryNoteStateEnum::DISPATCHED && (bool) $deliveryNote->date?->gte(now()->subDays(30)->startOfDay());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(PartnerShipment $partnerShipment): array
    {
        $warehouse = $partnerShipment->warehouse;

        return $partnerShipment->customsInvoices()->get()->map(fn (PartnerCustomsInvoice $customsInvoice) => [
            'reference' => $customsInvoice->reference,
            'label'     => $customsInvoice->label,
            'lines'     => $customsInvoice->number_lines,
            'units'     => (float) $customsInvoice->units,
            'value'     => (float) $customsInvoice->amount,
            'download'  => $warehouse ? route('grp.org.warehouses.show.dispatching.customs_invoices.export', [$customsInvoice->organisation->slug, $warehouse->slug, $customsInvoice->id]) : null,
        ])->all();
    }

    /**
     * @param Collection<int, DeliveryNote> $eligible
     * @param Collection<int, DeliveryNote> $selected
     * @param array<int, string> $unknown
     *
     * @return array<string, mixed>
     */
    private function preview(OrgPartner $orgPartner, Collection $eligible, Collection $selected, array $unknown): array
    {
        $lines = $selected->isEmpty() ? collect() : GetPartnerShipmentCustomsLines::make()->handle($orgPartner, $selected->pluck('id')->all());

        return [
            'partner'                  => $orgPartner->partner->code.' ('.$orgPartner->partner->name.')',
            'currency'                 => $orgPartner->organisation->currency->code,
            'next_consolidated'               => StorePartnerShipment::nextReference($orgPartner, SerialReferenceModelEnum::PARTNER_SHIPMENT),
            'next_customs_invoice'     => StorePartnerShipment::nextReference($orgPartner),
            'delivery_notes'           => $eligible->map(fn (DeliveryNote $deliveryNote) => [
                'reference' => $deliveryNote->reference,
                'invoice'   => $deliveryNote->invoices ?: 'not invoiced yet',
                'shop'      => $deliveryNote->shop_code,
                'state'     => $deliveryNote->state->value,
                'date'      => $deliveryNote->date?->toDateString(),
                'weight_kg' => round((float) $deliveryNote->effective_weight / 1000, 1),
                'proposed'  => $this->isProposed($deliveryNote),
            ])->values()->all(),
            'not_found'                => $unknown,
            'waiting_for_invoices'     => $selected->filter(fn (DeliveryNote $deliveryNote) => !$deliveryNote->invoices || !in_array($deliveryNote->state, [DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED], true))->pluck('reference')->values()->all(),
            'would_make'               => $lines->map(fn ($splitLines, string $split) => [
                'label'           => GetPartnerShipmentCustomsLines::splitLabel($orgPartner, $split),
                'lines'           => $splitLines->count(),
                'units'           => round($splitLines->sum(fn ($line) => (float) $line->units), 3),
                'value'           => round($splitLines->sum(fn ($line) => (float) $line->amount), 2),
                'gross_weight_kg' => round($splitLines->sum(fn ($line) => (float) $line->weight), 3),
            ])->values()->all(),
            'missing_tariff_or_origin' => $lines->flatten(1)->where('is_incomplete', true)
                ->flatMap(fn ($line) => collect(json_decode($line->offenders ?? '[]', true))->pluck('part'))
                ->filter()->unique()->sort()->values()->all(),
            'last_consolidated'              => PartnerShipment::where('org_partner_id', $orgPartner->id)->latest('id')->limit(5)->get()
                ->map(fn (PartnerShipment $partnerShipment) => [
                    'consolidated'     => $partnerShipment->reference,
                    'date'             => $partnerShipment->date->toDateString(),
                    'state'            => IndexPartnerShipments::stateLabel($partnerShipment),
                    'customs_invoices' => $this->documents($partnerShipment),
                ])->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'   => $schema->string()->description('Slug or code of the shipping organisation, e.g. aroma')->required(),
            'partner'        => $schema->string()->description('Code or slug of the partner organisation the goods go to, e.g. ES or SK')->required(),
            'delivery_notes' => $schema->array()->items($schema->string())->description('References of the delivery notes going together (e.g. AR27204); default the proposed ones. Required to create'),
            'date'           => $schema->string()->description('Date of the consolidated delivery note and its customs invoices, YYYY-MM-DD; default today'),
            'number_pallets' => $schema->integer()->description('Pallets'),
            'create'         => $schema->boolean()->description('Users enrolled for procurement only: consolidate the notes and make the customs invoices when every note is invoiced'),
            'request_text'   => $schema->string()->description('The user\'s request, verbatim; required to create'),
        ];
    }
}
