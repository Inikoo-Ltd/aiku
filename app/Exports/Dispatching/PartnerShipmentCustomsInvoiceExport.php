<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Dispatching;

use App\Actions\Dispatching\PartnerShipment\GetPartnerShipmentCustomsLines;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Helpers\Address;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * One customs invoice of a partner shipment (HELP-4482), laid out like the ones staff made by hand:
 * shipper, consignee, number and date, the lines by tariff code and origin, totals and the UK
 * preferential origin declaration.
 */
class PartnerShipmentCustomsInvoiceExport implements FromArray, ShouldAutoSize
{
    public function __construct(public PartnerShipment $partnerShipment, public string $split)
    {
    }

    public function reference(): string
    {
        return $this->partnerShipment->documentReference(array_search($this->split, $this->partnerShipment->splits, true));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $shipment   = $this->partnerShipment;
        $orgPartner = $shipment->orgPartner;
        $firstNote  = $shipment->deliveryNotes()->with(['shop', 'customer'])->orderBy('delivery_notes.id')->first();
        $shop       = $firstNote?->shop;
        $consignee  = $firstNote?->customer;
        $seller     = $shipment->organisation;
        $currency   = $shop?->currency?->code ?? $seller->currency->code;
        $locale     = strtolower((string) $orgPartner->partner->country?->code);
        $hasLocale  = in_array($locale, ['es', 'sk'], true);

        $lines = GetPartnerShipmentCustomsLines::make()
            ->handle($orgPartner, $shipment->deliveryNotes()->pluck('delivery_notes.id')->all())
            ->get($this->split, collect());

        $taxNumber = $shop?->taxNumber;
        $eori      = $taxNumber && strtoupper((string) $taxNumber->country_code) === 'GB'
            ? preg_replace('/\D/', '', $taxNumber->number).'000'
            : null;

        $deliveryNotes = $shipment->deliveryNotes()->orderBy('reference')->pluck('reference');
        $invoices      = Invoice::query()
            ->whereIn('order_id', fn ($query) => $query->select('order_id')->from('delivery_note_order')
                ->whereIn('delivery_note_id', $shipment->deliveryNotes()->select('delivery_notes.id')))
            ->whereNull('deleted_at')
            ->orderBy('reference')
            ->pluck('reference');

        $rows = [
            [__('INVOICE'), '', '', '', '', '', $this->reference(), $shipment->date->format('d/m/Y'), GetPartnerShipmentCustomsLines::splitLabel($orgPartner, $this->split)],
            [],
            [__('Shipper/Exporter'), $this->party(
                $seller->name,
                $seller->address,
                $shop?->phone,
                $shop?->email,
                $taxNumber?->getFormattedTaxNumber(),
                $shop?->identity_document_number
            )],
            [__('Consignee'), $this->party(
                $consignee?->company_name ?: $orgPartner->partner->name,
                $consignee?->address ?? $orgPartner->partner->address,
                $consignee?->phone,
                $consignee?->email,
                $consignee?->taxNumber?->getFormattedTaxNumber(),
                null
            )],
            [__('Mode of transport'), __('By road')],
            [__('Shipping terms'), __('Delivered At Place (DAP) Incoterms 2020')],
            [__('Final destination'), implode(', ', array_filter([$consignee?->address?->locality ?? $orgPartner->partner->address?->locality, $consignee?->address?->country_code ?? $orgPartner->partner->address?->country_code]))],
            [__('Number of pallets'), $shipment->number_pallets],
            [__('Delivery notes'), $deliveryNotes->implode(', ')],
            [__('Invoices'), $invoices->implode(', ')],
            [],
            $this->withLocaleColumn([
                __('Product references'),
                __('Description'),
                __('UN number'),
                __('Tariff code'),
                __('Unit cost').' ('.$currency.')',
                __('Units'),
                __('Total').' ('.$currency.')',
                __('Net weight (kg)'),
                __('Gross weight (kg)'),
                __('Origin'),
            ], $hasLocale ? __('Description').' ('.strtoupper($locale).')' : null),
        ];

        foreach ($lines as $line) {
            $units  = (float) $line->trade_units ?: (float) $line->units;
            $rows[] = $this->withLocaleColumn([
                $line->parts ?? '',
                $line->description ?? '',
                $line->un_numbers ?? '',
                $line->tariff_code ?? '',
                $units > 0 ? round((float) $line->amount / $units, 2) : 0,
                $units,
                (float) $line->amount,
                (float) $line->net_weight,
                (float) $line->weight,
                $line->origin ?? '',
            ], $hasLocale ? ($line->{'description_'.$locale} ?? '') : null);
        }

        $rows[] = $this->withLocaleColumn([
            __('TOTAL'),
            '',
            '',
            '',
            '',
            $lines->sum(fn ($line) => (float) $line->trade_units ?: (float) $line->units),
            round($lines->sum(fn ($line) => (float) $line->amount), 2),
            round($lines->sum(fn ($line) => (float) $line->net_weight), 3),
            round($lines->sum(fn ($line) => (float) $line->weight), 3),
        ], $hasLocale ? '' : null);

        $isNonPreferential = $this->split === 'main' && $orgPartner->split_gb_origin;
        if ($eori && !$isNonPreferential) {
            $rows[] = [];
            $rows[] = [__(
                'The exporter of the products covered by this document (EORI: GB:eori XI EORI: XI:eori) declares that, except where otherwise clearly indicated, the products are of United Kingdom preferential origin. :place, :date, :exporter',
                [
                    'eori'     => $eori,
                    'place'    => $seller->address?->locality,
                    'date'     => $shipment->date->format('d.m.Y'),
                    'exporter' => $seller->name,
                ]
            )];
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $row
     *
     * @return array<int, mixed>
     */
    private function withLocaleColumn(array $row, mixed $localeValue): array
    {
        if ($localeValue !== null) {
            array_splice($row, 2, 0, [$localeValue]);
        }

        return $row;
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
}
