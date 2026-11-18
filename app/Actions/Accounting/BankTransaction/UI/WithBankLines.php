<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankTransaction\UI;

use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * What Bank incoming and Bank outgoing share: the lines of one organisation or of the organisations of the group a
 * person may see, the period, currency, date and amount filters, and amounts in the organisation's or the group's currency.
 */
trait WithBankLines
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    public const array PERIODS = ['90d', '1y', 'all'];

    /** @var array<int, int>|null */
    public ?array $organisationIds = null;

    public ?int $groupCurrencyId = null;

    public ?string $organisationSlug = null;

    private string $period = '90d';

    private ?string $currency = null;

    private ?string $from = null;

    private ?string $to = null;

    private ?float $min = null;

    private ?float $max = null;

    protected function direction(): BankTransactionDirectionEnum
    {
        return BankTransactionDirectionEnum::IN;
    }

    /**
     * The amount of a line as a positive figure: money paid out is stored negative.
     */
    public function amountSql(): string
    {
        return $this->direction() === BankTransactionDirectionEnum::OUT ? '(-bank_transactions.amount)' : 'bank_transactions.amount';
    }

    protected function readFilters(Organisation|Group $organisation, ActionRequest $request): void
    {
        $this->period   = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : '90d';
        $this->currency = in_array($request->query('currency'), $this->currencies($organisation), true) ? $request->query('currency') : null;
        $this->from     = $this->validDate($request->query('from'));
        $this->to       = $this->from ? $this->validDate($request->query('to')) : null;
        $this->min      = $this->validAmount($request->query('min'));
        $this->max      = $this->validAmount($request->query('max'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function filtersQuery(): array
    {
        return array_filter([
            'period'   => $this->from || $this->period === '90d' ? null : $this->period,
            'currency' => $this->currency,
            'from'     => $this->from,
            'to'       => $this->to,
            'min'      => $this->min,
            'max'      => $this->max,
            'org'      => $this->organisationSlug,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function organisationOptions(Group $group, User $user): array
    {
        return self::accountingOrganisations($group, $user)->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->organisationIds === null) {
            return $this->authorizeInOrganisation($request);
        }

        return self::accountingOrganisations(group(), $request->user())->isNotEmpty();
    }

    /**
     * The organisations of the group whose accounting the user may see.
     *
     * @return Collection<int, Organisation>
     */
    public static function accountingOrganisations(Group $group, User $user): Collection
    {
        if (!$user->hasPermissionTo(GroupPermissionsEnum::FINANCIALS->value)) {
            return collect();
        }

        return $group->organisations()->orderBy('name')->get()->filter(fn (Organisation $organisation) => $user->authTo("accounting.$organisation->id.view"))->values();
    }

    /**
     * Read the whole group: only the organisations the user may see, or the one of them picked, in the group's currency.
     */
    public function forGroup(Group $group, User $user, ?string $organisationSlug = null): static
    {
        $organisations          = self::accountingOrganisations($group, $user);
        $picked                 = $organisationSlug ? $organisations->firstWhere('slug', $organisationSlug) : null;
        $this->organisationSlug = $picked?->slug;
        $this->organisationIds  = $picked ? [$picked->id] : $organisations->pluck('id')->all();
        $this->groupCurrencyId  = $group->currency_id;

        return $this;
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|QueryBuilder
     * @param  TQuery  $query
     * @return TQuery
     */
    public function bankLines($query, Organisation|Group $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null)
    {
        $since = match (true) {
            $from !== null    => Carbon::parse($from)->startOfDay(),
            $period === 'all' => null,
            $period === '1y'  => now()->subYear(),
            default           => now()->subDays(90),
        };

        return $query
            ->when(
                $organisation instanceof Group,
                fn ($query) => $query->whereIn('bank_transactions.organisation_id', $this->organisationIds ?? []),
                fn ($query) => $query->where('bank_transactions.organisation_id', $organisation->id)
            )
            ->where('bank_transactions.direction', $this->direction())
            ->where('bank_transactions.status', BankTransactionStatusEnum::BOOKED)
            ->when($this->direction() === BankTransactionDirectionEnum::OUT, fn ($query) => $query->where('bank_transactions.amount', '<', 0))
            ->when(isset($this->shop), fn ($query) => $query->whereRaw('bank_transactions.id in ('.IndexBankIncoming::shopLeadSql($this->shop).')'))
            ->when($since, fn ($query) => $query->where('bank_transactions.booked_at', '>=', $since))
            ->when($to, fn ($query) => $query->where('bank_transactions.booked_at', '<=', Carbon::parse($to)->endOfDay()))
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->join('organisations', 'organisations.id', 'bank_transactions.organisation_id')
            ->join('currencies as org_currencies', 'org_currencies.id', '=', DB::raw($this->targetCurrencySql()))
            ->when($currency, fn ($query) => $query->where('currencies.code', $currency))
            ->when($min !== null, fn ($query) => $query->whereRaw($this->orgAmountSql().' >= ?', [$min]))
            ->when($max !== null, fn ($query) => $query->whereRaw($this->orgAmountSql().' <= ?', [$max]));
    }

    /**
     * The currency amounts are shown in: the organisation's, or the group's when the whole group is read.
     */
    protected function targetCurrencySql(): string
    {
        return $this->groupCurrencyId !== null ? (string) $this->groupCurrencyId : 'organisations.currency_id';
    }

    /**
     * The line in the target currency, at the last rate known on the day it was booked; null when no rate is known.
     */
    public function orgAmountSql(): string
    {
        $rate = fn (string $currencyId) => "case when $currencyId = (select id from currencies where code = '".addslashes(config('app.currency_exchange.pivot'))."') then 1 else (select ce.exchange from currency_exchanges ce where ce.currency_id = $currencyId and ce.date <= bank_transactions.booked_at::date order by ce.date desc limit 1) end";

        $target = $this->targetCurrencySql();

        $amount = $this->amountSql();

        return "case when bank_transactions.currency_id = $target then $amount else round($amount * ({$rate($target)}) / nullif(({$rate('bank_transactions.currency_id')}), 0), 2) end";
    }

    /**
     * @return array<int, string>
     */
    protected function currencies(Organisation|Group $organisation): array
    {
        return BankTransaction::query()
            ->join('currencies', 'currencies.id', 'bank_transactions.currency_id')
            ->when(
                $organisation instanceof Group,
                fn ($query) => $query->whereIn('bank_transactions.organisation_id', $this->organisationIds ?? []),
                fn ($query) => $query->where('bank_transactions.organisation_id', $organisation->id)
            )
            ->where('bank_transactions.direction', $this->direction())
            ->distinct()
            ->orderBy('currencies.code')
            ->pluck('currencies.code')
            ->all();
    }

    protected function validAmount(mixed $amount): ?float
    {
        return is_numeric($amount) && $amount >= 0 ? (float) $amount : null;
    }

    protected function validDate(mixed $date): ?string
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : null;
    }

    /**
     * Reads "57 277,82", "1.419,30" or "1,419.30" as an amount; whatever is not a number is not searched as one.
     */
    protected function searchedAmount(string $value): ?string
    {
        $amount = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $value);
        if (str_contains($amount, ',') && str_contains($amount, '.')) {
            $amount = strrpos($amount, ',') > strrpos($amount, '.') ? str_replace('.', '', $amount) : str_replace(',', '', $amount);
        }
        $amount = str_replace(',', '.', $amount);

        return preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) ? $amount : null;
    }

    /**
     * How many lines each section holds and what they add up to in the organisation's currency, under the filters chosen.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function sectionTotals(Organisation|Group $organisation, string $period, ?string $currency, ?string $from, ?string $to, ?float $min = null, ?float $max = null): array
    {
        $bySection = $this->bankLines(BankTransaction::query(), $organisation, $period, $currency, $from, $to, $min, $max)
            ->selectRaw(static::BUCKET_SQL.' as section, count(*) as lines, sum('.$this->orgAmountSql().') as total')
            ->groupByRaw('1')
            ->get()
            ->keyBy('section');

        return collect(static::SECTIONS)->mapWithKeys(fn (string $section) => [
            $section => ['count' => (int) ($bySection->get($section)->lines ?? 0), 'total' => round((float) ($bySection->get($section)->total ?? 0), 2)],
        ])->all();
    }

    /**
     * How many lines the section holds in each currency and what they add up to in the organisation's currency.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function currencyTotals(Organisation|Group $organisation, string $section, string $period, ?string $from, ?string $to, ?float $min = null, ?float $max = null, ?string $provider = null): array
    {
        return $this->bankLines(BankTransaction::query(), $organisation, $period, null, $from, $to, $min, $max)
            ->whereRaw('('.static::BUCKET_SQL.') = ?', [$section])
            ->when($provider, fn ($query) => $query->whereRaw('('.static::PROVIDER_SQL.') = ?', [$provider]))
            ->selectRaw('currencies.code, count(*) as lines, sum('.$this->orgAmountSql().') as total')
            ->groupBy('currencies.code')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->code => ['count' => (int) $row->lines, 'total' => round((float) $row->total, 2)]])
            ->all();
    }

    /**
     * The Dashboard entry and one entry per section, each with how many lines it holds.
     *
     * @param array<string, array{0: string, 1: array<int, string>}> $sections label and icon by section
     * @param array<string, int> $counts
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public static function sectionsNavigation(Organisation|Group $organisation, string $slug, array $sections, array $counts, array $query = []): array
    {
        $base       = ($organisation instanceof Group ? 'grp.financials.' : 'grp.org.accounting.').$slug.'.';
        $parameters = $organisation instanceof Group ? [] : ['organisation' => $organisation->slug];
        $entry      = fn (string $label, string $route, array $icon, ?int $number = null) => array_filter([
            'label'    => $label,
            'root'     => $base.$route,
            'route'    => [
                'name'       => $base.$route,
                'parameters' => [...$parameters, ...($route === 'index' ? array_intersect_key($query, ['period' => true, 'org' => true]) : $query)],
            ],
            'leftIcon' => ['icon' => $icon, 'tooltip' => $label],
            'number'   => $number,
        ], fn ($value) => $value !== null);

        return [
            $entry(__('Dashboard'), 'index', ['fal', 'fa-chart-line']),
            ...collect($sections)->map(fn (array $section, string $key) => $entry($section[0], $key, $section[1], $counts[$key] ?? 0))->values()->all(),
        ];
    }
}
