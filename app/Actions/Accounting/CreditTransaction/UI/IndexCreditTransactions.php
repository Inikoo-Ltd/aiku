<?php

/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-14h-18m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\CreditTransaction\UI;

use App\Actions\Accounting\CreditTransaction\UI\Traits\WithCreditTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Accounting\CreditTransactionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndexCreditTransactions extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCreditTransactions;

    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(CreditTransaction::class);

        $query->where('credit_transactions.customer_id', $customer->id);

        $this->applyBaseJoins($query);

        return $query->defaultSort('credit_transactions.id')
            ->select($this->getBaseColumns())
            ->allowedSorts(['amount', 'running_amount', 'type', 'created_at', 'payment_reference'])
            ->allowedFilters([$this->getGlobalSearchFilter()])
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

            $this->addBaseTableColumns($table);

            $table->column(key: 'actions', label: __('Actions'));
        };
    }

    public function jsonResponse(LengthAwarePaginator $creditTransactions): AnonymousResourceCollection
    {
        return CreditTransactionsResource::collection($creditTransactions);
    }

}
