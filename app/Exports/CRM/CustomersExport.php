<?php

namespace App\Exports\CRM;

use App\Actions\CRM\Customer\GetCustomersQueryByRecipe;
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
    public function __construct(public Organisation|Shop $parent, public array $recipe = [])
    {
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Customer|Builder
    {
        $key = $this->parent instanceof Shop ? 'shop_id' : 'organisation_id';

        $query = Customer::where($key, $this->parent->id);

        if ($this->parent instanceof Shop && $this->recipeHasFilters()) {
            $recipeQuery = GetCustomersQueryByRecipe::run($this->parent->id, $this->recipe);

            $query->whereIn('customers.id', $recipeQuery->select('customers.id'));
        }

        return $query;
    }

    protected function recipeHasFilters(): bool
    {
        return count(array_diff_key($this->recipe, ['all_customers' => true])) > 0;
    }

    /**
     * Columns selected for streamed CSV exports, aligned with headings().
     *
     * @return array<int, string>
     */
    public function exportColumns(): array
    {
        return [
            'customers.id',
            'customers.slug',
            'customers.name',
            'customers.email',
            'customers.phone',
            'customers.contact_name',
            'customers.state',
            'customers.company_name',
            'customers.created_at',
        ];
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
            $row->state->value,
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
