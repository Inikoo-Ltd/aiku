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
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Http\Resources\Accounting\RefundPaymentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetRefundPayments extends OrgAction
{
    public function handle(Invoice $parent, $prefix = null): LengthAwarePaginator
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
        if (class_basename($parent) == 'Invoice') {

            $queryBuilder
            ->where('payments.type', PaymentTypeEnum::PAYMENT)
            ->leftJoin('model_has_payments', 'payments.id', 'model_has_payments.payment_id')
            ->where('model_has_payments.model_id', $parent->id)
            ->where('model_has_payments.model_type', 'Invoice')
            ->leftJoin('invoices', 'invoices.id', '=', 'model_has_payments.model_id')
            ->leftJoin('invoices as refunds', 'refunds.invoice_id', '=', 'invoices.id')
            ->where('refunds.in_process', false);
            // ->where('refunds.pay_status', InvoicePayStatusEnum::UNPAID);
        } else {
            abort(422);
        }

        // $queryBuilder->leftjoin('organisations', 'payments.organisation_id', '=', 'organisations.id');
        // $queryBuilder->leftjoin('shops', 'payments.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'payments.currency_id', 'currencies.id');


        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'payments.id',
                'payments.reference',
                'payments.status',
                'payments.date',
                'payments.amount',
                'payment_accounts.name as payment_account_name',
                'payment_accounts.slug as payment_accounts_slug',
                'payment_service_providers.slug as payment_service_providers_slug',
                'currencies.code as currency_code',
                DB::raw('ABS(SUM(refunds.payment_amount)) as refunded'),
            ])
            ->groupBy([
                'payments.id',
                'payment_account_name',
                'payment_accounts_slug',
                'payment_service_providers_slug',
                'currency_code'
            ])
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['reference', 'status', 'date'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function asController(Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $invoice;
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

    public function jsonResponse($payments): AnonymousResourceCollection
    {
        return RefundPaymentsResource::collection($payments);
    }
}
