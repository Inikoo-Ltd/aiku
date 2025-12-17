<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:40:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Ordering;

use App\Models\Ordering\Order;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class OrderTransactionTemplateExport implements FromArray, WithColumnFormatting
{
    protected ?Order $order;
    protected string $currencySymbol;

    public function __construct(?Order $order = null)
    {
        $this->order = $order;
        $this->currencySymbol = $order?->currency?->symbol ?? '$';
    }

    public function array(): array
    {
        $rows = [
            ['code', 'name', 'quantity', 'net', 'total']
        ];

        if ($this->order) {
            $transactions = $this->order->transactions()->with('historicAsset')->get();

            foreach ($transactions as $transaction) {
                $historicAsset = $transaction->historicAsset;

                if (!$historicAsset || !$historicAsset->code) {
                    continue;
                }

                $rows[] = [
                    $historicAsset->code,
                    $historicAsset->name,
                    $transaction->quantity_ordered ?? 0,
                    $transaction->net_amount ?? 0,
                    $transaction->gross_amount ?? 0,
                ];
            }
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => '"' . $this->currencySymbol . '"#,##0.00',
            'E' => '"' . $this->currencySymbol . '"#,##0.00',
        ];
    }
}
