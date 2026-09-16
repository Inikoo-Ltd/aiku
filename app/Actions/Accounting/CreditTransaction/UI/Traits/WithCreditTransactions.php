<?php

namespace App\Actions\Accounting\CreditTransaction\UI\Traits;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
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
            })
            ->leftJoin('model_has_payments as invoice_has_payments', function ($join) {
                $join->on('invoice_has_payments.payment_id', '=', 'payments.id')
                    ->where('invoice_has_payments.model_type', '=', 'Invoice');
            })
            ->leftJoin('invoices as credit_notes', function ($join) {
                $join->on('invoice_has_payments.model_id', '=', 'credit_notes.id')
                    ->where('credit_notes.type', '=', InvoiceTypeEnum::REFUND->value);
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
            'credit_transactions.data',
            'credit_notes.slug as credit_note_slug',
            'credit_notes.reference as credit_note_reference',
        ];
    }

    public function addBaseTableColumns(InertiaTable $table): void
    {
        $table->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, type: 'date_hm');
        $table->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'payment_reference', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'order_reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'credit_note_reference', label: __('Credit note'), canBeHidden: false);
        $table->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
        $table->column(key: 'running_amount', label: __('Running amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
    }
}
