<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Reports\BankFees;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Http\Resources\Accounting\BankFeesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexBankFees extends OrgAction
{
    use WithAccountingAuthorisation;

    private string $from;
    private string $to;

    /**
     * Fees the bank took (bank_transactions.fee_amount) next to fees absorbed on customers' short
     * transfers (payments on a bank_fee payment account), one row per currency and month.
     */
    public function handle(Organisation $organisation, string $from, string $to): LengthAwarePaginator
    {
        $start = Carbon::parse($from)->startOfDay();
        $end   = Carbon::parse($to)->endOfDay();

        $bank = DB::table('bank_transactions')
            ->where('organisation_id', $organisation->id)
            ->where('status', BankTransactionStatusEnum::BOOKED->value)
            ->where('fee_amount', '>', 0)
            ->whereBetween('booked_at', [$start, $end])
            ->selectRaw("currency_id, to_char(booked_at, 'YYYY-MM') as month, fee_amount as bank_fees, abs(amount) as bank_base, 0 as absorbed_fees");

        $dateColumn = 'coalesce(payments.date, payments.created_at)';

        $absorbed = DB::table('payments')
            ->join('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
            ->where('payments.organisation_id', $organisation->id)
            ->whereNull('payments.deleted_at')
            ->where('payment_accounts.type', PaymentAccountTypeEnum::BANK_FEE->value)
            ->where('payments.status', PaymentStatusEnum::SUCCESS->value)
            ->where('payments.state', '!=', PaymentStateEnum::CANCELLED->value)
            ->whereRaw("$dateColumn between ? and ?", [$start, $end])
            ->selectRaw("payments.currency_id, to_char($dateColumn, 'YYYY-MM') as month, 0 as bank_fees, 0 as bank_base, payments.amount as absorbed_fees");

        $combined = DB::query()
            ->fromSub($bank->unionAll($absorbed), 'u')
            ->selectRaw('currency_id, month, sum(bank_fees) as bank_fees, sum(bank_base) as bank_base, sum(absorbed_fees) as absorbed_fees')
            ->groupBy('currency_id', 'month');

        $query = Currency::query()
            ->fromSub($combined, 'fees')
            ->join('currencies', 'currencies.id', '=', 'fees.currency_id')
            ->select([
                DB::raw("fees.currency_id || '-' || fees.month as id"),
                'currencies.code as currency_code',
                'fees.month',
                'fees.bank_fees',
                'fees.bank_base',
                'fees.absorbed_fees',
            ]);

        return QueryBuilder::for($query)
            ->defaultSort('-month', 'currency_code')
            ->allowedSorts(['month', 'currency_code', 'bank_fees', 'absorbed_fees'])
            ->withPaginator(null, 100, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('row'), __('rows')])
                ->withEmptyState(['title' => __('No bank fees in this period')])
                ->column(key: 'month', label: __('Month'), sortable: true)
                ->column(key: 'currency_code', label: __('Currency'), sortable: true)
                ->column(key: 'bank_fees', label: __('Bank fees'), sortable: true, align: 'right')
                ->column(key: 'bank_base', label: __('Transfers with a fee'), align: 'right')
                ->column(key: 'bank_fee_percentage', label: __('Fee %'), align: 'right')
                ->column(key: 'absorbed_fees', label: __('Absorbed on customer transfers'), sortable: true, align: 'right');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        $valid = fn (mixed $date): ?string => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4)) ? $date : null;

        $to   = $valid($request->query('to')) ?? now()->toDateString();
        $from = $valid($request->query('from')) ?? Carbon::parse($to)->subMonths(11)->startOfMonth()->toDateString();

        [$this->from, $this->to] = $from <= $to ? [$from, $to] : [$to, $from];

        return $this->handle($organisation, $this->from, $this->to);
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        $title = __('Bank fees');

        return Inertia::render(
            'Org/Accounting/BankFees',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => ['fal', 'fa-percent'],
                    'title' => $title,
                ],
                'range'       => ['from' => $this->from, 'to' => $this->to],
                'data'        => BankFeesResource::collection($rows),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => $routeName, 'parameters' => $routeParameters],
                        'label' => __('Bank fees'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
