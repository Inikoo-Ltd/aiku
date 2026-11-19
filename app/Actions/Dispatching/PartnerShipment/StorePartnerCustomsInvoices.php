<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Makes a truck's customs invoices once every delivery note on it is invoiced (HELP-4482): one per
 * customs split, numbered from the partner's customs invoice counter, lines and header frozen as
 * issued, in English whoever makes them. Each shop invoice goes on one truck only.
 */
class StorePartnerCustomsInvoices extends OrgAction
{
    /**
     * Invoice ids of each delivery note on the truck (empty while the note is only packed).
     *
     * @return Collection<int, array<int, int>> keyed by delivery note id
     */
    public static function invoicesByDeliveryNote(PartnerShipment $partnerShipment): Collection
    {
        $rows = DB::table('partner_shipment_delivery_notes as psdn')
            ->leftJoin('delivery_note_order as dno', 'dno.delivery_note_id', '=', 'psdn.delivery_note_id')
            ->leftJoin('invoices', fn ($join) => $join->on('invoices.order_id', '=', 'dno.order_id')
                ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
                ->where('invoices.in_process', false)
                ->whereNull('invoices.deleted_at'))
            ->where('psdn.partner_shipment_id', $partnerShipment->id)
            ->select(['psdn.delivery_note_id', 'invoices.id as invoice_id'])
            ->get();

        return $rows->groupBy('delivery_note_id')->map(fn ($noteRows) => $noteRows->pluck('invoice_id')->filter()->unique()->values()->all());
    }

    /**
     * Notes on the consolidation that cannot go on its customs invoices yet: only finalised (or
     * dispatched) notes with their invoice can. Packed ones wait to be finalised; a cancelled one has
     * to come off (void the consolidation and consolidate again).
     *
     * @return array<int, string> "AR26886 (packed)"
     */
    public static function waitingForInvoices(PartnerShipment $partnerShipment): array
    {
        $invoices = self::invoicesByDeliveryNote($partnerShipment);

        return $partnerShipment->deliveryNotes()
            ->orderBy('reference')
            ->get(['delivery_notes.id', 'delivery_notes.reference', 'delivery_notes.state'])
            ->reject(fn ($deliveryNote) => in_array($deliveryNote->state, [DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED], true)
                && ($invoices[$deliveryNote->id] ?? []) !== [])
            ->map(fn ($deliveryNote) => $deliveryNote->reference.' ('.(DeliveryNoteStateEnum::labels()[$deliveryNote->state->value] ?? $deliveryNote->state->value).')')
            ->values()
            ->all();
    }

    /**
     * @throws \Throwable
     */
    public function handle(PartnerShipment $partnerShipment): PartnerShipment
    {
        $locale = app()->getLocale();
        app()->setLocale('en');
        try {
            return $this->store($partnerShipment);
        } finally {
            app()->setLocale($locale);
        }
    }

    /**
     * @throws \Throwable
     */
    private function store(PartnerShipment $partnerShipment): PartnerShipment
    {
        return DB::transaction(function () use ($partnerShipment) {
            DB::select('select pg_advisory_xact_lock(4482, ?)', [$partnerShipment->org_partner_id]);
            $partnerShipment = PartnerShipment::lockForUpdate()->findOrFail($partnerShipment->id);
            $orgPartner      = $partnerShipment->orgPartner;

            if ($partnerShipment->voided_at) {
                throw ValidationException::withMessages(['customs' => __('This consolidation is void')]);
            }
            if ($partnerShipment->customs_invoiced_at) {
                throw ValidationException::withMessages(['customs' => __('This consolidation already has its customs invoices')]);
            }

            $waiting = self::waitingForInvoices($partnerShipment);
            if ($waiting) {
                throw ValidationException::withMessages([
                    'customs' => __('Not finalised: :notes. Finalise them first; a cancelled one has to come off (void and consolidate again).', ['notes' => implode(', ', $waiting)]),
                ]);
            }

            $invoiceIds = self::invoicesByDeliveryNote($partnerShipment)->flatten()->unique()->values()->all();
            $takenIds   = DB::table('partner_shipment_invoices')->whereIn('invoice_id', $invoiceIds)->pluck('invoice_id')->all();

            $requestedIds = $partnerShipment->deliveryNotes()->pluck('delivery_notes.id')->all();

            $lines      = GetPartnerShipmentCustomsLines::make()->handle($orgPartner, $requestedIds);
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'customs' => __('Nothing is packed on this consolidation'),
                ]);
            }

            $incomplete = $lines->flatten(1)->where('is_incomplete', true);
            if ($incomplete->isNotEmpty()) {
                $parts = $incomplete->flatMap(fn ($line) => collect(json_decode($line->offenders ?? '[]', true))->pluck('part'))->filter()->unique()->sort()->values();

                throw ValidationException::withMessages([
                    'customs' => $parts->isEmpty()
                        ? __('Some packed lines have no SKO, so their tariff code and origin are unknown')
                        : __('Set the tariff code and country of origin of: :parts', ['parts' => $parts->implode(', ')]),
                ]);
            }

            $partnerShipment->invoices()->attach(array_values(array_diff($invoiceIds, $takenIds)));
            $header = [...$this->header($partnerShipment, $this->locale($orgPartner)), 'invoices' => Invoice::whereIn('id', $invoiceIds)->orderBy('reference')->pluck('reference')->all()];

            StorePartnerShipment::serialReference($orgPartner, SerialReferenceModelEnum::CUSTOMS_INVOICE);
            $locale = $this->locale($orgPartner);
            foreach ($lines as $split => $splitLines) {
                $customsInvoice = $partnerShipment->customsInvoices()->create([
                    'group_id'        => $orgPartner->group_id,
                    'organisation_id' => $orgPartner->organisation_id,
                    'org_partner_id'  => $orgPartner->id,
                    'split'           => $split,
                    'label'           => GetPartnerShipmentCustomsLines::splitLabel($orgPartner, $split),
                    'with_origin_declaration' => $header['eori'] !== null && !($split === 'main' && $orgPartner->split_gb_origin),
                    'reference'       => GetSerialReference::run($orgPartner, SerialReferenceModelEnum::CUSTOMS_INVOICE),
                    'date'            => $partnerShipment->date,
                    'number_lines'    => $splitLines->count(),
                    'units'           => $splitLines->sum(fn ($line) => (float) $line->units),
                    'amount'          => round($splitLines->sum(fn ($line) => (float) $line->amount), 2),
                    'net_weight'      => round($splitLines->sum(fn ($line) => (float) $line->net_weight), 3),
                    'gross_weight'    => round($splitLines->sum(fn ($line) => (float) $line->weight), 3),
                ]);

                foreach ($splitLines->values() as $position => $line) {
                    $customsInvoice->lines()->create([
                        'position'          => $position,
                        'tariff_code'       => $line->tariff_code,
                        'origin'            => $line->origin,
                        'parts'             => $line->parts,
                        'description'       => $line->description,
                        'local_description' => $locale ? $line->{'description_'.$locale} : null,
                        'un_numbers'        => $line->un_numbers,
                        'units'             => $line->units,
                        'amount'            => $line->amount,
                        'net_weight'        => $line->net_weight,
                        'gross_weight'      => $line->weight,
                    ]);
                }
            }

            $partnerShipment->update(['data' => $header, 'customs_invoiced_at' => now()]);

            return $partnerShipment;
        });
    }

    private function locale(OrgPartner $orgPartner): ?string
    {
        $locale = strtolower((string) $orgPartner->partner->country?->code);

        return in_array($locale, ['es', 'sk'], true) ? $locale : null;
    }

    /**
     * The header of the customs invoices as issued, so a later change to the shop, the customer or
     * the invoices never rewrites a document already handed to customs.
     *
     * @return array<string, mixed>
     */
    private function header(PartnerShipment $partnerShipment, ?string $locale): array
    {
        $orgPartner = $partnerShipment->orgPartner;
        $firstNote  = $partnerShipment->deliveryNotes()->with(['shop', 'customer'])->orderBy('delivery_notes.id')->first();
        $shop       = $firstNote?->shop;
        $consignee  = $firstNote?->customer;
        $seller     = $partnerShipment->organisation;
        $taxNumber  = $shop?->taxNumber;

        return [
            'currency'          => $shop?->currency?->code ?? $seller->currency->code,
            'locale'            => $locale,
            'seller_name'       => $seller->name,
            'seller_locality'   => $seller->address?->locality,
            'eori'              => $taxNumber && strtoupper((string) $taxNumber->country_code) === 'GB'
                ? preg_replace('/\D/', '', $taxNumber->number).'000'
                : null,
            'shipper'           => $this->party(
                $seller->name,
                $seller->address,
                $shop?->phone,
                $shop?->email,
                $taxNumber?->getFormattedTaxNumber(),
                $shop?->identity_document_number
            ),
            'consignee'         => $this->party(
                $consignee?->company_name ?: $orgPartner->partner->name,
                $consignee?->address ?? $orgPartner->partner->address,
                $consignee?->phone,
                $consignee?->email,
                $consignee?->taxNumber?->getFormattedTaxNumber(),
                null
            ),
            'final_destination' => implode(', ', array_filter([
                $consignee?->address?->locality ?? $orgPartner->partner->address?->locality,
                $consignee?->address?->country_code ?? $orgPartner->partner->address?->country_code,
            ])),
            'delivery_notes'    => $partnerShipment->deliveryNotes()->orderBy('reference')->pluck('reference')->all(),
            'invoices'          => $partnerShipment->invoices()->orderBy('reference')->pluck('reference')->all(),
        ];
    }

    private function party(string $name, ?Address $address, ?string $phone, ?string $email, ?string $taxNumber, ?string $registrationNumber): string
    {
        return implode('  ', array_filter([
            $name,
            $address ? implode(', ', array_filter([$address->address_line_1, $address->address_line_2, $address->locality, $address->postal_code, $address->country_code])) : null,
            $phone,
            $email,
            $taxNumber ? __('VAT').': '.$taxNumber : null,
            $registrationNumber ? __('Reg. No').': '.$registrationNumber : null,
        ]));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(WarehousePermissionsEnum::getDispatchingEditPermissionNames($this->organisation));
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, PartnerShipment $partnerShipment, ActionRequest $request): RedirectResponse
    {
        abort_unless($partnerShipment->organisation_id === $organisation->id && $warehouse->organisation_id === $organisation->id && $partnerShipment->warehouse_id === $warehouse->id, 404);
        $this->initialisation($organisation, $request);
        $this->handle($partnerShipment);

        return back();
    }
}
