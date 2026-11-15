<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the organisation's banks received in a period: by section, how it was placed, what waits for a person,
 * the trend against the period before, and where it came from. Movements of the bank itself (cashback, transfers
 * between our balances) are not money received: they are counted apart.
 */
class ShowBankIncomingDashboard extends OrgAction
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    private ?IndexBankIncoming $groupIndex = null;

    private string $period = '90d';

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $organisation, string $period = '90d', ?IndexBankIncoming $index = null): array
    {
        $index ??= IndexBankIncoming::make();
        $now     = now();
        $days    = match ($period) {
            '1y'    => 365,
            'all'   => null,
            default => 90,
        };
        $binDays = $days === 90 ? 7 : 30;
        $since   = $days ? $now->copy()->subDays($days) : null;

        $rows = $index->linesReceived(BankTransaction::query(), $organisation, 'all', null, null, null)
            ->when($since, fn ($query) => $query->where('bank_transactions.booked_at', '>=', $now->copy()->subDays($days * 2)))
            ->join('bank_connections', 'bank_connections.id', 'bank_transactions.bank_connection_id')
            ->selectRaw('bank_transactions.booked_at::date as day')
            ->selectRaw(IndexBankIncoming::BUCKET_SQL.' as section')
            ->selectRaw("case
                when bank_transactions.match_state is null and coalesce(bank_transactions.provider_type, '') not in ('".implode("', '", MatchBankTransactions::CUSTOMER_TRANSFER_TYPES)."') then 'own'
                when bank_transactions.match_state is null then 'unread'
                when bank_transactions.payment_id is null and bank_transactions.match_state in ('review', 'unmatched', 'matched') then 'waiting'
                when bank_transactions.match_data->>'paid_by' is not null or bank_transactions.match_data->>'dismissed_by' is not null then 'hand'
                else 'auto' end as placed")
            ->selectRaw('case when ('.IndexBankIncoming::BUCKET_SQL.") = 'payment_accounts' then ".IndexBankIncoming::PROVIDER_SQL.' end as provider')
            ->selectRaw('case when ('.IndexBankIncoming::BUCKET_SQL.") = 'needs_person' then coalesce(bank_transactions.match_data->>'reason', 'matches, not paid automatically') end as reason")
            ->selectRaw('currencies.code as currency, bank_connections.name as bank, organisations.name as organisation')
            ->selectRaw('count(*) as lines, sum(bank_transactions.amount) as amount, sum('.$index->orgAmountSql().') as org_amount')
            ->groupByRaw('1, 2, 3, 4, 5, 6, 7, 8')
            ->get()
            ->map(function ($row) use ($now, $since, $binDays) {
                $day           = Carbon::parse($row->day);
                $row->previous = $since !== null && $day->lt($since->copy()->startOfDay());
                $row->bin      = intdiv((int) $day->diffInDays(($row->previous ? $since : $now)->copy()->startOfDay(), true), $binDays);
                $row->lines    = (int) $row->lines;

                return $row;
            });

        $ownMovements = $rows->where('previous', false)->where('placed', 'own');
        $rows         = $rows->where('placed', '!=', 'own');
        $current      = $rows->where('previous', false);
        $previous     = $rows->where('previous', true);
        $sum      = fn (Collection $rows) => ['lines' => $rows->sum('lines'), 'amount' => round((float) $rows->sum('org_amount'), 2)];
        $ranked   = fn (Collection $rows, string $key, ?array $labels = null) => $rows->whereNotNull($key)->groupBy($key)
            ->map(fn (Collection $group, string $value) => ['key' => $value, 'label' => $labels[$value] ?? $value, ...$sum($group)])
            ->sortByDesc('amount')->values()->all();

        $bins   = $days ? (int) ceil($days / $binDays) : max(1, (int) $current->max('bin') + 1);
        $series = fn (Collection $rows) => collect(range($bins - 1, 0))->map(fn (int $bin) => round((float) $rows->where('bin', $bin)->sum('org_amount'), 2))->all();

        $waiting = $current->where('section', 'needs_person');
        $oldest  = $index->linesReceived(BankTransaction::query(), $organisation, 'all', null, null, null)
            ->whereRaw('('.IndexBankIncoming::BUCKET_SQL.") = 'needs_person'")
            ->min('bank_transactions.booked_at');

        return [
            'period'      => $period,
            'total'       => [...$sum($current), 'previous_amount' => $since ? $sum($previous)['amount'] : null],
            'sections'    => collect(IndexBankIncoming::SECTIONS)->map(fn (string $section) => [
                'key'             => $section,
                ...$sum($current->where('section', $section)),
                'previous_amount' => $since ? $sum($previous->where('section', $section))['amount'] : null,
                'waiting'         => $section === 'needs_person' ? 0 : $current->where('section', $section)->where('placed', 'waiting')->sum('lines'),
            ])->all(),
            'backlog'     => [
                ...$sum($waiting),
                'oldest'  => $oldest ? Carbon::parse($oldest)->toDateString() : null,
                'reasons' => array_slice($ranked($waiting, 'reason'), 0, 5),
            ],
            'own'         => $sum($ownMovements),
            'placement'   => collect(['auto', 'hand', 'waiting', 'unread'])->mapWithKeys(fn (string $placed) => [$placed => $sum($current->where('placed', $placed))])->all(),
            'trend'       => [
                'starts'   => collect(range($bins - 1, 0))->map(fn (int $bin) => $now->copy()->startOfDay()->subDays(($bin + 1) * $binDays - 1)->toDateString())->all(),
                'bin_days' => $binDays,
                'series'   => collect(IndexBankIncoming::SECTIONS)->mapWithKeys(fn (string $section) => [$section => $series($current->where('section', $section))])->all(),
                'previous' => $since ? $series($previous) : null,
            ],
            'providers'   => $ranked($current, 'provider', IndexBankIncoming::providerLabels()),
            'currencies'  => $current->groupBy('currency')
                ->map(fn (Collection $group, string $code) => ['key' => $code, 'label' => $code, ...$sum($group), 'native' => round((float) $group->sum('amount'), 2)])
                ->sortByDesc('amount')->values()->all(),
            'banks'       => $ranked($current, 'bank'),
            'organisations' => $organisation instanceof Group ? $ranked($current, 'organisation') : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $this->period = in_array($request->query('period'), IndexBankIncoming::PERIODS, true) ? $request->query('period') : '90d';

        return $this->handle($organisation, $this->period);
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $slug             = $request->query('org');
        $this->groupIndex = IndexBankIncoming::make()->forGroup(group(), $request->user(), is_string($slug) ? $slug : null);
        $this->initialisationFromGroup(group(), $request);
        $this->period = in_array($request->query('period'), IndexBankIncoming::PERIODS, true) ? $request->query('period') : '90d';

        return $this->handle(group(), $this->period, $this->groupIndex);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->groupIndex === null) {
            return $this->authorizeInOrganisation($request);
        }

        return IndexBankIncoming::accountingOrganisations(group(), $request->user())->isNotEmpty();
    }

    /**
     * @param array<string, mixed> $dashboard
     * @return array<string, int>
     */
    private function tabCounts(array $dashboard): array
    {
        $counts          = collect($dashboard['sections'])->pluck('lines', 'key')->all();
        $counts['other'] = ($counts['other'] ?? 0) + $dashboard['own']['lines'];

        return $counts;
    }

    public function htmlResponse(array $dashboard, ActionRequest $request): Response
    {
        $isGroup = $this->groupIndex !== null;
        $query   = array_filter(['period' => $this->period === '90d' ? null : $this->period, 'org' => $this->groupIndex?->organisationSlug]);
        $parent  = $isGroup ? $this->group : $this->organisation;

        return Inertia::render(
            'Org/Accounting/BankIncomingDashboard',
            [
                'breadcrumbs' => $isGroup ? IndexBankIncoming::groupBreadcrumbs() : IndexBankIncoming::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Bank incoming'),
                'pageHead'    => [
                    'title'         => __('Bank incoming'),
                    'icon'          => ['fal', 'fa-university'],
                    'subNavigation' => IndexBankIncoming::subNavigation($parent, $this->tabCounts($dashboard), $query),
                ],
                'query'       => $query,
                'organisation'  => $this->groupIndex?->organisationSlug,
                'organisations' => $isGroup
                    ? IndexBankIncoming::accountingOrganisations($this->group, $request->user())->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all()
                    : [],
                'routeBase'   => $isGroup ? 'grp.financials.bank_incoming.' : 'grp.org.accounting.bank_incoming.',
                'orgCurrency' => $parent->currency->code,
                'dashboard'   => $dashboard,
            ]
        );
    }
}
