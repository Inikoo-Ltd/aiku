<?php

namespace App\Exports\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
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
    public function __construct(
        protected Organisation $parent,
        protected string $startDate,
        protected string $endDate,
    ) {
    }

    public function query(): Builder
    {
        $query = Invoice::query()
            ->with([
                'customer.address',
                'order',
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
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_TEXT,
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

        $address     = $customer->address;
        $countryCode = $address->country_code ?? '';

        $isRefund   = $invoice->type === InvoiceTypeEnum::REFUND;
        $multiplier = $isRefund ? -1 : 1;

        $netAmount   = $multiplier * (float)$invoice->net_amount;
        $taxAmount   = $multiplier * (float)$invoice->tax_amount;
        $totalAmount = $multiplier * (float)$invoice->total_amount;

        $goodsAmount    = $multiplier * (float)($invoice->goods_amount ?? 0);
        $servicesAmount = $multiplier * (float)($invoice->services_amount ?? 0);
        $shippingAmount = $multiplier * (float)($invoice->shipping_amount ?? 0);
        $chargesAmount  = $multiplier * (float)($invoice->charges_amount ?? 0);

        $itemsAmount = $goodsAmount + $servicesAmount;

        // : R column - currently always 0, and is ok

        $taxPercentage = $netAmount != 0 ? abs(($taxAmount / $netAmount) * 100) : 0;

        $taxType = $this->determineTaxType($customer, $countryCode, $taxPercentage);

        return [
            $isRefund ? 'Refund' : 'Invoice',
            $invoice->reference,
            $order ? $order->reference : '',
            $customer->company_name ?: $customer->name,
            $customer->taxNumber->number ?? '',
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
        ];
    }

    protected function determineTaxType($customer, string $countryCode, float $taxPercentage): string
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
            if ($taxPercentage > 24) {
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
        return array_fill(0, 17, '');
    }
}
