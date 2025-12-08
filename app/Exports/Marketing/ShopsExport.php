<?php

namespace App\Exports\Marketing;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ShopsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Shop|Builder
    {
        return Shop::query();
    }

    /** @var Shop */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->company_name,
            $row->contact_name,
            $row->email,
            $row->phone,
            $row->identity_document_number,
            $row->identity_document_type,
            $row->location,
            $row->state->value,
            $row->type->value,
            $row->open_at,
            $row->closed_at,
            $row->country->name,
            $row->currency->code,
            $row->timezone->name,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Company Name',
            'Contact Name',
            'Email',
            'Phone',
            'Identity Document Number',
            'Identity Document Type',
            'Location',
            'State',
            'Type',
            'Open At',
            'Closed At',
            'Country Name',
            'Currency',
            'Timezone',
            'State',
            'Created At',
        ];
    }
}
