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

class SageInvoicesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
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
                'customer',
                'taxCategory',
            ])
            ->where('in_process', false)
            ->where('organisation_id', $this->parent->id);

        // Only apply date filter if dates are provided
        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Type',
            'Account Reference',
            'Nominal A/C Ref',
            'Department Code',
            'Date',
            'Reference',
            'Details',
            'Net Amount',
            'Tax Code',
            'Tax Amount',
            'Exchange Rate',
            'Extra Reference',
            'User Name',
            'Project Refn',
            'Cost Code Refn'
        ];
    }

    /** @var Invoice $invoice */
    public function map($invoice): array
    {
        $customer = $invoice->customer;

        if (!$customer) {
            return $this->emptyRow();
        }

        $accountingRef = $customer->is_credit_customer ? $customer->accounting_reference : '';

        // Determine nominal account code and department code based on customer
        [$nominalCode, $departmentCode] = $this->getAccountMapping($customer, $accountingRef);

        // Map tax category to Sage tax code
        $taxCode = $this->mapTaxCode($invoice->taxCategory);

        // Format amounts (negative for credit notes)
        $isRefund = $invoice->type === InvoiceTypeEnum::REFUND;
        $netAmount = $isRefund ? -abs((float)$invoice->net_amount) : (float)$invoice->net_amount;
        $taxAmount = $isRefund ? -abs((float)$invoice->tax_amount) : (float)$invoice->tax_amount;

        return [
            $isRefund ? 'SC' : 'SI',                            // Type
            $accountingRef,                                     // Account Reference
            $nominalCode,                                       // Nominal A/C Ref
            $departmentCode,                                    // Department Code
            $invoice->date->format('d/m/Y'),                    // Date (DD/MM/YYYY)
            $invoice->reference,                                // Reference
            $customer->name,                                    // Details
            number_format($netAmount, 2, '.', ''),              // Net Amount
            $taxCode,                                           // Tax Code
            number_format($taxAmount, 2, '.', ''),              // Tax Amount
            '1.00',                                             // Exchange Rate
            $invoice->reference,                                // Extra Reference
            'Aiku Sales',                                       // User Name
            '',                                                 // Project Refn
            '',                                                 // Cost Code Refn
        ];
    }

    protected function emptyRow(): array
    {
        return ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
    }

    protected function getAccountMapping($customer, string $accountingRef): array
    {
        // Check if fulfilment customer
        if ($customer->is_fulfilment) {
            return ['4011', '11'];
        }

        // Check if UK credit customer (non-fulfilment)
        if ($customer->is_credit_customer && !$customer->is_fulfilment) {
            return ['4008', '19'];
        }

        // Map based on accounting reference
        $mappings = [
            'DS01' => ['4000', '20'],
            'EX01' => ['4002', '22'],
            'UK01' => ['4000', '21'],
            'WH01' => ['4012', '7'],
            'AC01' => ['4000', '12'],
            'ESG01' => ['4005', '23'],
            'SLG01' => ['4003', '24'],
            'AR05' => ['4007', '25'],
        ];

        return $mappings[$accountingRef] ?? ['4000', '1'];
    }

    protected function mapTaxCode($taxCategory): string
    {
        if (!$taxCategory) {
            return 'T9';
        }

        $rate = (float)$taxCategory->rate;

        if ($rate >= 20) {
            return 'T1'; // Standard Rate 20%
        } elseif ($rate > 0) {
            return 'T1'; // Any positive rate, use standard
        } elseif ($rate == 0) {
            return 'T0'; // Zero Rate
        }

        return 'T9'; // No VAT
    }
}
