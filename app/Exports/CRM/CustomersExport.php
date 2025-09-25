<?php

namespace App\Exports\CRM;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public Organisation|Shop $parent;

    public function __construct(Organisation|Shop $parent)
    {
        $this->parent = $parent;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Customer|Builder
    {
        $key = $this->parent instanceof Shop ? 'shop_id' : 'organisation_id';

        return Customer::where($key, $this->parent->id);
    }

    /** @var Customer $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->name,
            $row->email,
            $row->phone,
            $row->contact_name,
            $row->state,
            $row->company_name,
            $row->created_at
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Name',
            'Email',
            'Phone',
            'Contact Name',
            'State',
            'Company Name',
            'Created At'
        ];
    }
}
