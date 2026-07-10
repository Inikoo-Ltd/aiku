<?php

namespace App\Exports\CRM;

use App\Actions\CRM\Customer\GetCustomersQueryByRecipe;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomersExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param array<int, string> $fields Selected field keys; empty means all fields.
     */
    public function __construct(public Organisation|Shop $parent, public array $recipe = [], public array $states = [], public array $statuses = [], public array $fields = [], public ?string $upcoming = null)
    {
    }

    /**
     * Ordered registry of exportable fields: key => [heading, select].
     *
     * @return array<string, array{heading: string, select: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'id'               => ['heading' => '#', 'select' => 'customers.id'],
            'reference'        => ['heading' => 'Reference', 'select' => 'customers.reference'],
            'name'             => ['heading' => 'Name', 'select' => 'customers.name'],
            'contact_name'     => ['heading' => 'Contact', 'select' => 'customers.contact_name'],
            'company_name'     => ['heading' => 'Company', 'select' => 'customers.company_name'],
            'fiscal_name'      => ['heading' => 'Fiscal Name', 'select' => 'customers.fiscal_name'],
            'email'            => ['heading' => 'Email', 'select' => 'customers.email'],
            'phone'            => ['heading' => 'Phone', 'select' => 'customers.phone'],
            'tax_number'       => ['heading' => 'Tax Number', 'select' => 'customers.identity_document_number'],
            'country'          => ['heading' => 'Country', 'select' => "customers.location->>1"],
            'contact_address'  => ['heading' => 'Contact Address', 'select' => self::addressExpression('contact_addr')],
            'delivery_address' => ['heading' => 'Delivery Address', 'select' => self::addressExpression('delivery_addr')],
            'balance'          => ['heading' => 'Account Balance', 'select' => 'customers.balance'],
            'state'            => ['heading' => 'State', 'select' => 'customers.state'],
            'status'           => ['heading' => 'Status', 'select' => 'customers.status'],
            'created_at'       => ['heading' => 'Creation Date', 'select' => 'customers.created_at'],
            'last_order_date'  => ['heading' => 'Last Order Date', 'select' => 'customer_stats.last_order_created_at'],
            'last_invoice_date' => ['heading' => 'Last Invoice Date', 'select' => 'customer_stats.last_invoiced_at'],
            'number_invoiced'  => ['heading' => 'Number Invoiced', 'select' => 'customer_stats.number_invoices_type_invoice'],
            'total_sales'      => ['heading' => 'Total Sales', 'select' => 'customer_stats.sales_all'],
        ];
    }

    protected static function addressExpression(string $alias): string
    {
        return "NULLIF(CONCAT_WS(', ', "
            ."NULLIF($alias.address_line_1, ''), "
            ."NULLIF($alias.address_line_2, ''), "
            ."NULLIF($alias.locality, ''), "
            ."NULLIF($alias.administrative_area, ''), "
            ."NULLIF($alias.postal_code, ''), "
            ."NULLIF($alias.country_code, '')"
            ."), '')";
    }

    /**
     * @return array<int, string>
     */
    public function selectedFields(): array
    {
        $keys = array_keys(self::fieldDefinitions());

        if (count($this->fields) === 0) {
            return $keys;
        }

        return array_values(array_intersect($keys, $this->fields));
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Customer|Builder
    {
        $key = $this->parent instanceof Shop ? 'shop_id' : 'organisation_id';

        $query = Customer::where($key, $this->parent->id);

        if ($this->parent instanceof Shop && $this->recipeHasFilters()) {
            $recipeQuery = GetCustomersQueryByRecipe::run($this->parent->id, $this->recipe);

            $query->whereIn('customers.id', $recipeQuery->select('customers.id'));
        }

        if (count($this->states) > 0) {
            $query->whereIn('customers.state', $this->states);
        }

        if (count($this->statuses) > 0) {
            $query->whereIn('customers.status', $this->statuses);
        }

        if ($this->parent instanceof Shop && in_array($this->upcoming, ['ready', 'out_of_stock'], true)) {
            $query->whereIn('customers.id', function ($sub) {
                $sub->select('customer_id')
                    ->from('upcoming_transactions')
                    ->where('upcoming_transactions.shop_id', $this->parent->id)
                    ->where('upcoming_transactions.state', UpcomingTransactionStateEnum::READY->value);

                if ($this->upcoming === 'out_of_stock') {
                    $sub->join('products', 'products.id', '=', 'upcoming_transactions.product_id')
                        ->whereIn('products.status', [ProductStatusEnum::OUT_OF_STOCK->value, ProductStatusEnum::NOT_FOR_SALE->value]);
                }
            });
        }

        return $query;
    }

    public function dataQuery(): Builder
    {
        $definitions = self::fieldDefinitions();

        $selects = array_map(
            fn ($field) => DB::raw($definitions[$field]['select'].' as '.$field),
            $this->selectedFields()
        );

        return $this->query()->toBase()
            ->leftJoin('addresses as contact_addr', 'customers.address_id', '=', 'contact_addr.id')
            ->leftJoin('addresses as delivery_addr', 'customers.delivery_address_id', '=', 'delivery_addr.id')
            ->leftJoin('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->select($selects)
            ->orderBy('customers.id');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->dataQuery()->get()
            ->map(fn ($row) => array_values((array) $row))
            ->all();
    }

    protected function recipeHasFilters(): bool
    {
        return count(array_diff_key($this->recipe, ['all_customers' => true])) > 0;
    }

    public function headings(): array
    {
        $definitions = self::fieldDefinitions();

        return array_map(
            fn ($field) => $definitions[$field]['heading'],
            $this->selectedFields()
        );
    }
}
