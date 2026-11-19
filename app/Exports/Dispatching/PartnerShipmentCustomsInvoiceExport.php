<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Dispatching;

use App\Models\Dispatching\PartnerCustomsInvoice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * One customs invoice of a partner shipment (HELP-4482), laid out like the ones staff made by hand:
 * shipper, consignee, number and date, the lines by tariff code and origin, totals and the UK
 * preferential origin declaration.
 */
class PartnerShipmentCustomsInvoiceExport implements FromArray, ShouldAutoSize
{
    public function __construct(public PartnerCustomsInvoice $customsInvoice)
    {
    }

    public function reference(): string
    {
        return $this->customsInvoice->reference;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $locale = app()->getLocale();
        app()->setLocale('en');
        try {
            return $this->rows();
        } finally {
            app()->setLocale($locale);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rows(): array
    {
        $shipment   = $this->customsInvoice->partnerShipment;
        $header     = $shipment->data;
        $currency   = $header['currency'];
        $locale     = $header['locale'];
        $hasLocale  = $locale !== null;

        $lines = $this->customsInvoice->lines()->get();

        $rows = [];
        if ($shipment->voided_at) {
            $rows[] = [sprintf('VOID %s: %s', $shipment->voided_at->format('d/m/Y'), $shipment->void_reason)];
        }

        $rows = [
            ...$rows,
            [__('INVOICE'), '', '', '', '', '', $this->reference(), $this->customsInvoice->date->format('d/m/Y'), $this->customsInvoice->label],
            [],
            [__('Shipper/Exporter'), $header['shipper']],
            [__('Consignee'), $header['consignee']],
            [__('Mode of transport'), __('By road')],
            [__('Shipping terms'), __('Delivered At Place (DAP) Incoterms 2020')],
            [__('Final destination'), $header['final_destination']],
            [__('Number of pallets'), $shipment->number_pallets],
            [__('Delivery notes'), implode(', ', $header['delivery_notes'])],
            [__('Invoices'), implode(', ', $header['invoices'])],
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
            $units  = (float) $line->units;
            $rows[] = $this->withLocaleColumn([
                $line->parts ?? '',
                $line->description ?? '',
                $line->un_numbers ?? '',
                $line->tariff_code ?? '',
                $units > 0 ? round((float) $line->amount / $units, 2) : 0,
                $units,
                (float) $line->amount,
                (float) $line->net_weight,
                (float) $line->gross_weight,
                $line->origin ?? '',
            ], $hasLocale ? ($line->local_description ?? '') : null);
        }

        $rows[] = $this->withLocaleColumn([
            __('TOTAL'),
            '',
            '',
            '',
            '',
            $lines->sum(fn ($line) => (float) $line->units),
            round($lines->sum(fn ($line) => (float) $line->amount), 2),
            round($lines->sum(fn ($line) => (float) $line->net_weight), 3),
            round($lines->sum(fn ($line) => (float) $line->gross_weight), 3),
        ], $hasLocale ? '' : null);

        if ($this->customsInvoice->with_origin_declaration) {
            $rows[] = [];
            $rows[] = [sprintf(
                'The exporter of the products covered by this document (EORI: GB%1$s XI EORI: XI%1$s) declares that, except where otherwise clearly indicated, the products are of United Kingdom preferential origin. %2$s, %3$s, %4$s',
                $header['eori'],
                $header['seller_locality'],
                $this->customsInvoice->date->format('d.m.Y'),
                $header['seller_name']
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
}
