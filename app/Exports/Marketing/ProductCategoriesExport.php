<?php

namespace App\Exports\Marketing;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductCategoriesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|ProductCategory|Builder
    {
        return ProductCategory::query();
    }

    /** @var ProductCategory */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->shop->name,
            $row->type,
            $row->state->value,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Shop Name',
            'Type',
            'Is Family',
            'State',
            'Created At',
        ];
    }
}
