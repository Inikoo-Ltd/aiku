<?php

namespace App\Actions\Accounting\CreditTransaction\UI\Traits;

use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

trait WithCreditTransactions
{
    public function getGlobalSearchFilter(): AllowedFilter
    {
        return AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                // Cast credit_transactions.amount as char so it is searchable using ILIKE function on PSQL
                $query->whereRaw("credit_transactions.amount::text ILIKE ?", ["%$value%"])
                    ->orWhereAnyWordStartWith('credit_transactions.type', $value);
            });
        });
    }

    public function applyBaseJoins(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->leftJoin('payments', 'credit_transactions.payment_id', '=', 'payments.id')
            ->leftJoin('currencies', 'credit_transactions.currency_id', '=', 'currencies.id')
            ->leftJoin('model_has_payments', function ($join) {
                $join->on('model_has_payments.payment_id', '=', 'payments.id')
                    ->where('model_has_payments.model_type', '=', 'Order');
            })
            ->leftJoin('orders', function ($join) {
                $join->on('model_has_payments.model_id', '=', 'orders.id');
            });
    }

    public function getBaseColumns(): array
    {
        return [
            'credit_transactions.id',
            'credit_transactions.date as created_at',
            'credit_transactions.type',
            'credit_transactions.amount',
            'credit_transactions.running_amount',
            'payments.reference as payment_reference',
            'payments.id as payment_id',
            'payments.type as payment_type',
            'currencies.code as currency_code',
            'orders.slug as order_slug',
            'orders.reference as order_reference',
            'credit_transactions.notes',
        ];
    }

    public function addBaseTableColumns(InertiaTable $table): void
    {
        $table->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, type: 'date_hm');
        $table->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'payment_reference', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'order_reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
        $table->column(key: 'running_amount', label: __('Running amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
    }
}
