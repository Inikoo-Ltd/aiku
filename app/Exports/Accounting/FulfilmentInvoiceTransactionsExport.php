<?php

/*
 * author Arya Permana - Kirin
 * created on 21-03-2025-09h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Exports\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Billables\Service;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FulfilmentInvoiceTransactionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    protected $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|InvoiceTransaction|Builder
    {
        return InvoiceTransaction::query()->where('invoice_id', $this->invoice->id);
    }

    /** @var Location */
    public function map($row): array
    {
        $palletData = 'no data';

        if ($row->model_type == 'Service') {
            $service = Service::find($row->model_id);
            if ($service->is_pallet_handling == true) {
                $pallet = Pallet::find($row->data['pallet_id']);
                $palletData = $pallet->reference;
                $palletRef = $pallet->customer_reference;
            }
        } elseif (isset($row->recurringBillTransaction)) {
            $palletData = $row->recurringBillTransaction->item->reference;
            $palletRef = $row->recurringBillTransaction->item->customer_reference;
        }

        return [
            $row->id,
            $row->model_type,
            $row->historicAsset->code,
            $row->historicAsset->name,
            $palletData,
            $palletRef,
            $row->quantity,
            $row->invoice->currency->symbol.$row->net_amount,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Type',
            'Code',
            'Name',
            'Pallet ID',
            'Pallet Reference',
            'Quantity',
            'Net',
        ];
    }
}
