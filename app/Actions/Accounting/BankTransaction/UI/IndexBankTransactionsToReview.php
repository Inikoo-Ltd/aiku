<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Http\Resources\Accounting\BankTransactionsToReviewResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Money received by the organisation's banks that a person still has to place: lines matching sent to review, and
 * matched lines that were not paid automatically (auto-pay off, or booked before it was switched on).
 */
class IndexBankTransactionsToReview extends OrgAction
{
    use WithAccountingPagesAuthorisation;

    public const array SCOPES = ['review', 'unmatched', 'dismissed', 'payouts'];

    public const array PERIODS = ['90d', '1y', 'all'];

    private string $scope = 'review';

    private string $period = '90d';

    private ?string $currency = null;

    private ?string $from = null;

    private ?string $to = null;

    public function handle(Organisation $organisation, string $scope = 'review', string $period = '90d', $prefix = null, ?string $currency = null, ?string $from = null, ?string $to = null): LengthAwarePaginator
    {
        $since = match (true) {
            $from !== null  => Carbon::parse($from)->startOfDay(),
            $period === 'all' => null,
            $period === '1y'  => now()->subYear(),
            default         => now()->subDays(90),
        };

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('bank_transactions.reference', $value)
                    ->orWhereAnyWordStartWith('bank_transactions.counterparty_name', $value)
                    ->orWhereWith('bank_connections.name', $value)
                    ->orWhere('orders.reference', '=', strtoupper($value));
                $amount = $this->searchedAmount($value);
                if ($amount !== null) {
                    $query->orWhere('bank_transactions.amount', $amount);
                }
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(BankTransaction::class)
            ->where('bank_transactions.organisation_id', $organisation->id)
            ->where('bank_transactions.direction', BankTransactionDirectionEnum::IN)
            ->where('bank_transactions.status', BankTransactionStatusEnum::BOOKED)
            ->whereNull('bank_transactions.payment_id')
            ->when($since, fn ($query) => $query->where('bank_transactions.booked_at', '>=', $since))
            ->when($to, fn ($query) => $query->where('bank_transactions.booked_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($currency, fn ($query) => $query->where('currencies.code', $currency))
            ->whereIn('bank_transactions.match_state', match ($scope) {
                'unmatched' => [BankTransactionMatchStateEnum::UNMATCHED],
                'dismissed' => [BankTransactionMatchStateEnum::DISMISSED],
                'payouts'   => [BankTransactionMatchStateEnum::CARD_PROCESSOR_PAYOUT],
                default     => [BankTransactionMatchStateEnum::REVIEW, BankTransactionMatchStateEnum::MATCHED],
            })
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id')
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->leftJoin('orders', 'orders.id', 'bank_transactions.order_id')
            ->leftJoin('shops', 'shops.id', 'orders.shop_id')
            ->select([
                'bank_transactions.id',
                'bank_transactions.booked_at',
                'bank_transactions.amount',
                'bank_transactions.counterparty_name',
                'bank_transactions.reference',
                'bank_transactions.match_state',
                'bank_transactions.match_data',
                'bank_connections.name as bank',
                'currencies.code as currency_code',
                'orders.reference as order_reference',
                'orders.slug as order_slug',
                'shops.slug as shop_slug',
            ])
            ->defaultSort('-booked_at')
            ->allowedSorts(['booked_at', 'amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $this->scope  = in_array($request->query('scope'), self::SCOPES, true) ? $request->query('scope') : 'review';
        $this->period = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : '90d';
        $this->currency = in_array($request->query('currency'), $this->currencies($organisation), true) ? $request->query('currency') : null;
        $this->from     = $this->validDate($request->query('from'));
        $this->to       = $this->from ? $this->validDate($request->query('to')) : null;

        return $this->handle($organisation, $this->scope, $this->period, currency: $this->currency, from: $this->from, to: $this->to);
    }

    /**
     * @return array<int, string>
     */
    private function currencies(Organisation $organisation): array
    {
        return BankTransaction::query()
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->where('bank_transactions.organisation_id', $organisation->id)
            ->where('bank_transactions.direction', BankTransactionDirectionEnum::IN)
            ->distinct()
            ->orderBy('currencies.code')
            ->pluck('currencies.code')
            ->all();
    }

    private function validDate(mixed $date): ?string
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : null;
    }

    /**
     * Reads "57 277,82", "1.419,30" or "1,419.30" as an amount; whatever is not a number is not searched as one.
     */
    private function searchedAmount(string $value): ?string
    {
        $amount = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value);
        if (str_contains($amount, ',') && str_contains($amount, '.')) {
            $amount = strrpos($amount, ',') > strrpos($amount, '.') ? str_replace('.', '', $amount) : str_replace(',', '', $amount);
        }
        $amount = str_replace(',', '.', $amount);

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) ? $amount : null;
    }

    public function htmlResponse(LengthAwarePaginator $bankTransactions, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/BankTransactionsToReview',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Bank review'),
                'pageHead'    => [
                    'title' => __('Bank review'),
                    'icon'  => ['fal', 'fa-university'],
                ],
                'canEdit'        => $request->user()->authTo("accounting.{$this->organisation->id}.edit"),
                'organisationId' => $this->organisation->id,
                'scope'          => $this->scope,
                'period'         => $this->from ? null : $this->period,
                'currency'       => $this->currency,
                'currencies'     => $this->currencies($this->organisation),
                'from'           => $this->from,
                'to'             => $this->to,
                'data'        => BankTransactionsToReviewResource::collection($bankTransactions),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('Nothing to review'),
                    'description' => __('Every payment the banks received is placed on an order, marked already paid or marked not for an order.'),
                ]);

            $table->column(key: 'booked_at', label: __('Date'), sortable: true);
            $table->column(key: 'bank', label: __('Bank'));
            $table->column(key: 'amount', label: __('Amount'), sortable: true, align: 'right');
            $table->column(key: 'counterparty_name', label: __('Paid by'));
            $table->column(key: 'reference', label: __('Bank reference'));
            $table->column(key: 'order_reference', label: __('Order'));
            $table->column(key: 'actions', label: '');
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.accounting.bank_review.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Bank review'),
                    ],
                ],
            ]
        );
    }
}
