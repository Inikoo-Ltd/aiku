<?php

namespace App\Exports\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MontanaInvoicesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    private const int ES_STANDARD = 30;
    private const int ES_STANDARD_SURCHARGE = 51;
    private const int ES_REDUCED = 52;
    private const int ES_REDUCED_SURCHARGE = 53;

    public function __construct(
        protected Organisation $parent,
        protected ?string $startDate = null,
        protected ?string $endDate = null,
    ) {
    }

    public function query(): Builder
    {
        $query = Invoice::query()
            ->select('invoices.*')
            ->addSelect([
                'line_tax_category_ids' => InvoiceTransaction::query()
                    ->selectRaw("string_agg(DISTINCT tax_category_id::text, ',')")
                    ->whereColumn('invoice_id', 'invoices.id'),
            ])
            ->with([
                'customer',
                'address',
                'deliveryAddress',
                'order.deliveryAddress',
                'originalInvoice',
                'currency',
                'taxCategory',
            ])
            ->where('in_process', false)
            ->where('organisation_id', $this->parent->id);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        return $query->orderBy('date')->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'ID',
            'Order ID',
            'Cliente',
            'Numero fiscal',
            'País',
            'Fecha',
            'M',
            'TIPO FRA',
            'Artíc',
            'envío',
            'Carg',
            'R',
            'Neto',
            'Impuestos',
            '%IVA',
            'TOTAL',
            'Tax code: ES-SR',
            'Tax code: ES-SR+RE',
            'Tax code: ES-RR+RE',
            'País envío',
            'Factura original',
            'Tax code: ES-RR',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function map($row): array
    {
        $invoice = $row;

        $customer = $invoice->customer;
        $order    = $invoice->order;

        if (!$customer) {
            return $this->emptyRow();
        }

        $countryCode         = $invoice->address->country_code ?? '';
        $deliveryCountryCode = ($invoice->deliveryAddress ?? $order?->deliveryAddress)?->country_code ?? '';

        // Refund is already minus from DB, no need to times that by -1
        $isRefund   = $invoice->type === InvoiceTypeEnum::REFUND;

        $netAmount   = (float)$invoice->net_amount;
        $taxAmount   = (float)$invoice->tax_amount;
        $totalAmount = (float)$invoice->total_amount;

        $goodsAmount    = (float)($invoice->goods_amount ?? 0);
        $servicesAmount = (float)($invoice->services_amount ?? 0);
        $shippingAmount = (float)($invoice->shipping_amount ?? 0);
        $chargesAmount  = (float)($invoice->charges_amount ?? 0);

        $itemsAmount = $goodsAmount + $servicesAmount;

        // : R column - currently always 0, and is ok

        $taxPercentage = $netAmount != 0 ? abs(($taxAmount / $netAmount) * 100) : 0;

        $taxType = $this->determineTaxType($customer, $countryCode, $taxPercentage, $invoice->tax_category_id);

        $taxPerCategory = $this->taxPerCategory($invoice);

        return [
            $isRefund ? 'Refund' : 'Invoice',
            $invoice->reference,
            $order ? $order->reference : '',
            $customer->company_name ?: $customer->name,
            (function () use ($invoice, $countryCode) {
                $taxNumber = $invoice->tax_number ?? '';

                if ($taxNumber === '') {
                    return '';
                }

                $taxNumberClean = preg_replace('/\s+/', '', $taxNumber);

                if ($countryCode !== '' && strncasecmp($taxNumberClean, $countryCode, strlen($countryCode)) === 0) {
                    $taxNumberClean = substr($taxNumberClean, strlen($countryCode));
                }

                return $taxNumberClean;
            })(),
            $countryCode,
            $invoice->date->format('Y-m-d H:i'),
            $invoice->currency->code ?? 'EUR',
            $taxType,
            number_format($itemsAmount, 2, '.', ''),
            number_format($shippingAmount, 2, '.', ''),
            number_format($chargesAmount, 2, '.', ''),
            '0',
            number_format($netAmount, 2, '.', ''),
            number_format($taxAmount, 2, '.', ''),
            number_format($taxPercentage, 2, '.', ''),
            number_format($totalAmount, 2, '.', ''),
            $this->formatCategoryTax($taxPerCategory, self::ES_STANDARD),
            $this->formatCategoryTax($taxPerCategory, self::ES_STANDARD_SURCHARGE),
            $this->formatCategoryTax($taxPerCategory, self::ES_REDUCED_SURCHARGE),
            $deliveryCountryCode,
            $invoice->originalInvoice->reference ?? '',
            $this->formatCategoryTax($taxPerCategory, self::ES_REDUCED),
        ];
    }

    /**
     * @return array<int, float>
     */
    protected function taxPerCategory(Invoice $invoice): array
    {
        $headerOnly = [$invoice->tax_category_id => (float)$invoice->tax_amount];

        $lineTaxCategoryIds = array_filter(explode(',', (string)$invoice->getAttribute('line_tax_category_ids')));

        if (!$lineTaxCategoryIds || $lineTaxCategoryIds === [(string)$invoice->tax_category_id]) {
            return $headerOnly;
        }

        $breakdown = $this->taxBreakdown($invoice);

        if (!$breakdown) {
            return $headerOnly;
        }

        $taxPerCategory = array_filter(
            array_column($breakdown, 'tax_amount', 'tax_category_id'),
            fn (float $taxAmount) => round($taxAmount, 2) != 0
        );

        return $taxPerCategory ?: $headerOnly;
    }

    /**
     * @return array<int, array{tax_category_id: int, name: string, rate: float, net_amount: float, tax_amount: float}>
     */
    protected function taxBreakdown(Invoice $invoice): array
    {
        return $invoice->taxBreakdown();
    }

    /**
     * @param  array<int, float>  $taxPerCategory
     */
    protected function formatCategoryTax(array $taxPerCategory, int $taxCategoryId): string
    {
        if (!array_key_exists($taxCategoryId, $taxPerCategory)) {
            return '';
        }

        return number_format($taxPerCategory[$taxCategoryId], 2, '.', '');
    }

    protected function determineTaxType($customer, string $countryCode, float $taxPercentage, ?int $taxCategoryId = null): string
    {
        $euCountries = [
            'AT',
            'BE',
            'BG',
            'CY',
            'CZ',
            'DE',
            'DK',
            'EE',
            'GR',
            'EL',
            'FI',
            'FR',
            'HR',
            'HU',
            'IE',
            'IT',
            'LT',
            'LU',
            'LV',
            'MT',
            'NL',
            'PL',
            'PT',
            'RO',
            'SE',
            'SI',
            'SK'
        ];

        if ($countryCode === 'ES') {
            if ($taxPercentage > 24 || ($taxPercentage > 0 && $taxCategoryId === self::ES_STANDARD_SURCHARGE)) {
                return 'T4';
            }
            if ($taxPercentage > 0) {
                return 'T3';
            }
        }

        if (in_array($countryCode, $euCountries)) {
            if ($taxPercentage > 0) {
                return 'T5';
            }

            return 'T1';
        }

        if ($taxPercentage == 0) {
            return 'T2';
        }

        return 'T5';
    }

    protected function emptyRow(): array
    {
        return array_fill(0, count($this->headings()), '');
    }
}
