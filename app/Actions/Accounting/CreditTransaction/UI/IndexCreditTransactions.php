<?php
/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-14h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\CreditTransaction\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\CreditTransactionsResource;
use App\Http\Resources\CRM\CustomerFavouritesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCreditTransactions extends OrgAction
{
    private Customer $parent;

    public function handle(Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('credit_transactions.amount', $value)
                    ->orWhereAnyWordStartWith('credit_transactions.type', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(CreditTransaction::class);
        if($parent instanceof Customer)
        {
            $query->where('credit_transactions.customer_id', $parent->id);
        }

        $query->leftJoin('payments', 'credit_transactions.payment_id', '=', 'payments.id');
        $query->leftJoin('currencies', 'credit_transactions.currency_id', '=', 'currencies.id');

        return $query->defaultSort('credit_transactions.id')
            ->select([
                'credit_transactions.id',
                'credit_transactions.type',
                'credit_transactions.amount',
                'credit_transactions.running_amount',
                'payments.reference as payment_reference',
                'payments.type as payment_type',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['amount', 'running_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $stats     = $parent->stats;
            $noResults = __("Customer has no credit transactions");


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_credit_transactions ?? 0
                    ]
                );


            $table->column(key: 'type', label: __('type'), canBeHidden: false, searchable: true);
            $table->column(key: 'payment_reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'running_amount', label: __('running amount'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {

        $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.view");

        return $request->user()->authTo("crm.{$this->shop->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $creditTransactions): AnonymousResourceCollection
    {
        return CreditTransactionsResource::collection($creditTransactions);
    }

}
