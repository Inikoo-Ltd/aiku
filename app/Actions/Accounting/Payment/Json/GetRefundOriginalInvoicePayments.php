<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Payment\Json;

use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Http\Resources\Accounting\RefundPaymentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetRefundOriginalInvoicePayments extends OrgAction
{
    public function handle(Invoice $refund, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Payment::class);
        $queryBuilder
            ->where('payments.type', PaymentTypeEnum::PAYMENT)
            ->leftJoin('model_has_payments', 'payments.id', 'model_has_payments.payment_id')
            ->where('model_has_payments.model_id', $refund->original_invoice_id)
            ->where('model_has_payments.model_type', 'Invoice')
            ->where('payments.status', PaymentStatusEnum::SUCCESS);


        $queryBuilder->leftJoin('currencies', 'payments.currency_id', 'currencies.id');


        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'payments.id',
                'payments.reference',
                'payments.status',
                'payments.total_refund as refunded',
                'payments.date',
                'payments.amount',
                'payment_accounts.code as payment_account_code',
                'payment_accounts.type as payment_account_type',
                'payment_accounts.name as payment_account_name',
                'payment_accounts.slug as payment_account_slug',
                'payment_service_providers.slug as payment_service_providers_slug',
                'currencies.code as currency_code',
            ])
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['reference', 'status', 'date'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function asController(Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

    public function jsonResponse($payments): AnonymousResourceCollection
    {
        return RefundPaymentsResource::collection($payments);
    }
}
