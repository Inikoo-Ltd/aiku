<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\DepositRequest\DepositRequestStateEnum;
use App\Models\Accounting\BankTransaction;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

/**
 * HELP-4864: what a client's banks paid out to its agent (any connected bank; the payee linked to the agent or its
 * organisation) settles the agent's approved money requests as "Set as paid" does, the bank line standing as the
 * bank statement. Lines whose amount the agent received adds up exactly to what is approved and due are recorded on
 * their own; any other line is offered to the client's payers on the Money tab to confirm with one click.
 *
 * Lines count from the oldest approved open request's request date (a transfer can leave before the approval), and
 * never before the last transfer someone recorded by hand, which already stands for the money sent until then. While a
 * transfer recorded by hand falls in that window nothing is recorded on its own, so the same money is never counted twice.
 * A line the bank reverses after it was recorded is only flagged (SyncWiseBankConnection::paidLineChanged).
 */
class MatchAgentMoneyTransfers
{
    use AsAction;

    public string $commandSignature = 'agent-money:match-bank {organisation? : organisation slug}';

    public const string DISMISSED = 'agent_money_dismissed';

    public const string UNDONE = 'agent_money_undone';

    public const string LINE_KEY = 'bank_line_key';

    /**
     * Fio and Tatra book a transfer days after it was sent and set as paid by hand, so a hand record this long before
     * the window still stops recording on its own.
     */
    public const int HAND_RECORD_SLACK_DAYS = 7;

    public function handle(Organisation $organisation): int
    {
        $recorded = 0;
        OrgAgent::where('organisation_id', $organisation->id)
            ->whereIn('agent_id', DepositRequest::where('organisation_id', $organisation->id)->whereNotNull('approved_at')->where('state', DepositRequestStateEnum::REQUESTED)->select('agent_id'))
            ->get()
            ->each(function (OrgAgent $orgAgent) use (&$recorded) {
                try {
                    $recorded += $this->autoRecord($orgAgent);
                } catch (Throwable $exception) {
                    Sentry::captureException($exception);
                }
            });

        return $recorded;
    }

    /**
     * The window is fixed before the first line is recorded, so a set split over two requests stays one set once the
     * first line settles the older request; the set is recorded whole or not at all.
     */
    public function autoRecord(OrgAgent $orgAgent): int
    {
        $transfers = DB::transaction(function () use ($orgAgent) {
            OrgAgent::whereKey($orgAgent->id)->lockForUpdate()->first();
            $since = $this->windowStart($orgAgent);
            $due   = $this->due($orgAgent);
            if (!$since || $due <= 0.005 || $this->handRecordedSince($orgAgent, $since->copy()->subDays(self::HAND_RECORD_SLACK_DAYS))) {
                return collect();
            }

            $lines   = $this->candidates($orgAgent, $since)->reject(fn (array $line) => $line['dismissed'] || $line['undone'])->values();
            $allSum  = $lines->isNotEmpty() && $lines->every(fn (array $line) => $line['received'] !== null) && abs($lines->sum('received') - $due) <= 0.005;
            $oneLine = $lines->filter(fn (array $line) => $line['received'] !== null && abs($line['received'] - $due) <= 0.005);
            $exact   = $allSum ? $lines : ($oneLine->count() === 1 ? $oneLine : collect());

            return $exact->flatMap(fn (array $line) => $this->recordLine($orgAgent, $line, null));
        });

        $this->notify($transfers, null);

        return $transfers->pluck('bank_transaction_id')->unique()->count();
    }

    /**
     * A payer confirms one bank line: in the agent's currency what the agent received, or what is due when the line was
     * sent in another currency (its amount kept as the amount sent).
     *
     * @return Collection<int, AgentPayment>
     */
    public function payFromLine(OrgAgent $orgAgent, BankTransaction $bankTransaction, ?User $payer, ?float $shownAmount = null): Collection
    {
        $transfers = DB::transaction(function () use ($orgAgent, $bankTransaction, $payer, $shownAmount) {
            OrgAgent::whereKey($orgAgent->id)->lockForUpdate()->first();
            $line = $this->candidates($orgAgent)->first(fn (array $line) => $line['line']->id === $bankTransaction->id);
            if (!$line) {
                throw ValidationException::withMessages(['bank_transaction' => __('This bank transfer can no longer be set as paid.')]);
            }
            if ($shownAmount !== null && abs(($line['received'] ?? $this->due($orgAgent)) - $shownAmount) > 0.005) {
                throw ValidationException::withMessages(['bank_transaction' => __('The amount due has changed. Reload the page and check it again.')]);
            }

            return $this->recordLine($orgAgent, $line, $payer);
        });

        $this->notify($transfers, $payer);

        return $transfers;
    }

    /**
     * @param  array{line: BankTransaction, received: float|null, key: string}  $line
     *
     * @return Collection<int, AgentPayment>
     */
    private function recordLine(OrgAgent $orgAgent, array $line, ?User $payer): Collection
    {
        $bankTransaction     = BankTransaction::whereKey($line['line']->id)->lockForUpdate()->firstOrFail();
        $sentInAgentCurrency = $bankTransaction->currency_id === $orgAgent->agent->organisation->currency_id;

        $transfers = PayAgentMoneyRequests::make()->handle($orgAgent, [
            'date'                 => self::bookedDate($bankTransaction, $orgAgent),
            'amount'               => $line['received'] ?? $this->due($orgAgent),
            'original_amount'      => $sentInAgentCurrency ? null : abs((float) $bankTransaction->amount),
            'original_currency_id' => $sentInAgentCurrency ? null : $bankTransaction->currency_id,
            'reference'            => $bankTransaction->reference,
            'bank_transaction_id'  => $bankTransaction->id,
            self::LINE_KEY         => $line['key'],
        ]);
        AgentPayment::whereIn('id', $transfers->pluck('id'))->update(['created_by' => $payer?->id]);

        return $transfers;
    }

    /**
     * @param  Collection<int, AgentPayment>  $transfers
     */
    private function notify(Collection $transfers, ?User $payer): void
    {
        foreach ($transfers as $transfer) {
            try {
                NotifyMoneyRequestPaid::run($transfer->depositRequest, $transfer, $payer);
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
            }
        }
    }

    /**
     * The day the bank booked the line, where the client is: Fio and Tatra book at local midnight.
     */
    public static function bookedDate(BankTransaction $line, OrgAgent $orgAgent): string
    {
        return $line->booked_at->copy()->setTimezone($orgAgent->organisation->timezone?->name ?? config('app.timezone'))->toDateString();
    }

    /**
     * Outgoing booked lines of the client's banks paid to the agent in the window, not recorded or set aside yet. The
     * window opens the day before the oldest approved open request: Fio and Tatra book lines at local midnight, the
     * previous day in UTC.
     *
     * A line set aside ("Not this one") or whose record was undone is still listed, flagged, so a payer can still
     * set it as paid; neither is ever recorded on its own again.
     *
     * @return Collection<int, array{line: BankTransaction, received: float|null, key: string, dismissed: bool, undone: bool}>
     */
    public function candidates(OrgAgent $orgAgent, ?Carbon $since = null): Collection
    {
        $since ??= $this->windowStart($orgAgent);
        if (!$since) {
            return collect();
        }
        $lastByHand = $this->lastHandRecordedAt($orgAgent);
        $since      = $lastByHand && $lastByHand->gt($since) ? $lastByHand : $since;
        $agent      = $orgAgent->agent;
        $currency   = $agent->organisation->currency->code;
        $settings   = $orgAgent->refresh()->settings ?? [];
        $dismissed  = array_flip(Arr::get($settings, self::DISMISSED, []));
        $undone     = array_flip(Arr::get($settings, self::UNDONE, []));

        $used     = AgentPayment::query()
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where(fn ($query) => $query->whereNotNull('data->'.self::LINE_KEY)->orWhereNotNull('bank_transaction_id'))
            ->get(['data', 'bank_transaction_id']);
        $usedKeys = $used->map(fn (AgentPayment $agentPayment) => Arr::get($agentPayment->data ?? [], self::LINE_KEY))->filter()->flip();
        $usedIds  = $used->pluck('bank_transaction_id')->filter()->flip();

        return BankTransaction::where('bank_transactions.organisation_id', $orgAgent->organisation_id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->where('status', BankTransactionStatusEnum::BOOKED)
            ->where('booked_at', '>=', $since)
            ->whereExists(fn ($query) => $query->from('bank_payees')
                ->whereColumn('bank_payees.group_id', 'bank_transactions.group_id')
                ->whereColumn('bank_payees.key', 'bank_transactions.payee_key')
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('bank_payees.model_type', 'Agent')->where('bank_payees.model_id', $agent->id))
                    ->orWhere(fn ($query) => $query->where('bank_payees.model_type', 'Organisation')->where('bank_payees.model_id', $agent->organisation_id))))
            ->with(['currency:id,code', 'bankConnection:id,name'])
            ->orderBy('booked_at')
            ->orderBy('id')
            ->get()
            ->map(fn (BankTransaction $line) => ['line' => $line, 'received' => self::received($line, $currency), 'key' => $key = self::lineKey($line), 'dismissed' => isset($dismissed[$key]), 'undone' => isset($undone[$key])])
            ->reject(fn (array $line) => $usedKeys->has($line['key']) || $usedIds->has($line['line']->id))
            ->values();
    }

    /**
     * "Not this one": kept on the client's agent, by the transfer's key, so bank re-matching never brings it back.
     */
    public function dismiss(OrgAgent $orgAgent, BankTransaction $bankTransaction): void
    {
        DB::transaction(function () use ($orgAgent, $bankTransaction) {
            $orgAgent = OrgAgent::whereKey($orgAgent->id)->lockForUpdate()->firstOrFail();
            $line     = $this->candidates($orgAgent)->first(fn (array $line) => $line['line']->id === $bankTransaction->id && !$line['dismissed']);
            if (!$line) {
                throw ValidationException::withMessages(['bank_transaction' => __('This bank transfer is no longer offered.')]);
            }
            $settings = $orgAgent->settings ?? [];
            $settings[self::DISMISSED][] = $line['key'];
            $orgAgent->update(['settings' => $settings]);
        });
    }

    /**
     * Takes back what was set as paid from one bank line (wrong match, or the bank reversed it): its transfers are
     * removed, their requests open again, and the line is offered again but never recorded on its own.
     *
     * @return Collection<int, AgentPayment> the transfers removed
     */
    public function undo(OrgAgent $orgAgent, string $key, ?User $undoer = null): Collection
    {
        $transfers = DB::transaction(function () use ($orgAgent, $key) {
            $orgAgent  = OrgAgent::whereKey($orgAgent->id)->lockForUpdate()->firstOrFail();
            $transfers = self::recordedFromLine($orgAgent, $key)->get();
            if ($transfers->isEmpty()) {
                throw ValidationException::withMessages(['bank_transaction' => __('Nothing set as paid from this bank transfer is left to undo.')]);
            }
            $transfers->each(fn (AgentPayment $transfer) => DeleteAgentPayment::make()->handle($transfer));

            $settings                 = $orgAgent->settings ?? [];
            $settings[self::UNDONE]   = array_values(array_unique([...Arr::get($settings, self::UNDONE, []), $key]));
            $settings[self::DISMISSED] = array_values(array_diff(Arr::get($settings, self::DISMISSED, []), [$key]));
            $orgAgent->update(['settings' => $settings]);

            return $transfers;
        });

        foreach ($transfers->groupBy('deposit_request_id') as $requestTransfers) {
            try {
                NotifyMoneyRequestPaymentUndone::run($requestTransfers->first()->depositRequest, round($requestTransfers->sum(fn (AgentPayment $transfer) => (float) $transfer->amount), 2), $undoer);
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
            }
        }

        return $transfers;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AgentPayment>
     */
    public static function recordedFromLine(OrgAgent $orgAgent, string $key): \Illuminate\Database\Eloquent\Builder
    {
        return AgentPayment::where('agent_id', $orgAgent->agent_id)
            ->where('organisation_id', $orgAgent->organisation_id)
            ->whereNotNull('deposit_request_id')
            ->where('data->'.self::LINE_KEY, $key);
    }

    /**
     * What the agent got, in its currency: the bank's own figure for what arrived when it gives one, else the line's
     * amount when it was sent in that currency; null when it was sent in another currency.
     */
    public static function received(BankTransaction $line, string $currencyCode): ?float
    {
        $transfer = Arr::get($line->data, 'transfer');
        if (is_array($transfer) && isset($transfer['targetCurrency'], $transfer['targetValue'])) {
            return $transfer['targetCurrency'] === $currencyCode ? round((float) $transfer['targetValue'], 2) : null;
        }

        return $line->currency->code === $currencyCode ? round(abs((float) $line->amount), 2) : null;
    }

    /**
     * The same transfer keeps its key when the bank feed replaces the line that stands for it.
     */
    public static function lineKey(BankTransaction $line): string
    {
        return $line->bank_connection_id.':'.(Arr::get($line->data, 'transfer.id') ?? $line->provider_transaction_id);
    }

    public function due(OrgAgent $orgAgent): float
    {
        return round(PayAgentMoneyRequests::approvedOpen($orgAgent)->get()->sum(fn (DepositRequest $depositRequest) => max(0, PayAgentMoneyRequest::outstanding($depositRequest))), 2);
    }

    private function windowStart(OrgAgent $orgAgent): ?Carbon
    {
        $requestedAt = PayAgentMoneyRequests::approvedOpen($orgAgent)->reorder()->min('requested_at');

        return $requestedAt ? Carbon::parse($requestedAt)->subDay()->startOfDay() : null;
    }

    /**
     * Money sent to the agent that no bank line stands for: recorded by hand by the client, or by the agent in its ledger.
     *
     * @return \Illuminate\Database\Eloquent\Builder<AgentPayment>
     */
    private function handRecorded(OrgAgent $orgAgent): \Illuminate\Database\Eloquent\Builder
    {
        return AgentPayment::where('agent_id', $orgAgent->agent_id)
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('type', AgentPaymentTypeEnum::TRANSFER_RECEIVED)
            ->whereNull('data->'.self::LINE_KEY);
    }

    private function lastHandRecordedAt(OrgAgent $orgAgent): ?Carbon
    {
        $at = $this->handRecorded($orgAgent)->max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    private function handRecordedSince(OrgAgent $orgAgent, ?Carbon $since): bool
    {
        return $since !== null && $this->handRecorded($orgAgent)->where('created_at', '>=', $since)->exists();
    }

    public function asCommand(Command $command): int
    {
        $organisations = $command->argument('organisation')
            ? Organisation::where('slug', $command->argument('organisation'))->get()
            : Organisation::all();

        foreach ($organisations as $organisation) {
            $command->line($organisation->slug.': '.$this->handle($organisation).' bank lines recorded');
        }

        return 0;
    }
}
