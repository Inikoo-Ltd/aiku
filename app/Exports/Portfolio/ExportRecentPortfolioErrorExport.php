<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:40:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Portfolio;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportRecentPortfolioErrorExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithEvents
{
    use Exportable;

    protected Upload $upload;

    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
    }

    public function query()
    {
        return UploadRecord::query()
            ->where('upload_id', $this->upload->id)
            ->where('status', 'failed');
    }

    public function map($row): array
    {
        /** @var UploadRecord $row */
        $uploadRecord = $row;
        return [
            Arr::get($uploadRecord->values, 'sku'),
            Arr::get($uploadRecord->values, 'title'),
            Arr::get($uploadRecord->errors, '0')
        ];
    }

    public function headings(): array
    {
        return [
            ['Sku', 'Title', 'Error']
        ];
    }


    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:B1')->getFont()->setBold(true);
            }
        ];
    }
}
