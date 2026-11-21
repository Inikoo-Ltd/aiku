<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The client sends one bank transfer for everything it approved and records it once: the transfer is split over the
 * approved requests, oldest first, each part recorded and settled as PayAgentMoneyRequest does for a single request.
 */
class PayAgentMoneyRequests extends OrgAction
{
    private OrgAgent $orgAgent;

    /**
     * @param  array{date: string, amount: float|string, original_amount?: float|string|null, original_currency_id?: int|null, reference?: string|null, receipt?: UploadedFile|null, bank_transaction_id?: int|null, bank_line_key?: string|null}  $modelData
     *
     * @return Collection<int, AgentPayment>
     */
    public function handle(OrgAgent $orgAgent, array $modelData): Collection
    {
        $receipt = Arr::get($modelData, 'receipt');
        $media   = $receipt ? PayAgentMoneyRequest::saveBankStatement($orgAgent->agent, $receipt) : null;
        $userId  = $this->asAction ? null : request()->user()?->id;

        return DB::transaction(function () use ($orgAgent, $modelData, $media, $userId) {
            $requests = self::approvedOpen($orgAgent)->lockForUpdate()->get();
            $dues     = $requests->mapWithKeys(fn (DepositRequest $depositRequest) => [$depositRequest->id => PayAgentMoneyRequest::outstanding($depositRequest)])
                ->filter(fn (float $due) => $due > 0.005);

            $amount = round((float) $modelData['amount'], 2);
            if ($dues->isEmpty()) {
                throw ValidationException::withMessages(['amount' => __('Nothing approved is waiting to be paid.')]);
            }
            if ($amount > $dues->sum() + 0.005) {
                throw ValidationException::withMessages(['amount' => __('That is more than is still due on the approved requests.')]);
            }

            $originalAmount = Arr::get($modelData, 'original_amount') ? round((float) $modelData['original_amount'], 2) : null;
            $left           = $amount;
            $originalLeft   = $originalAmount;
            $transfers      = collect();

            foreach ($requests->whereIn('id', $dues->keys()) as $depositRequest) {
                if ($left <= 0.005) {
                    break;
                }
                $part   = round(min($left, $dues[$depositRequest->id]), 2);
                $isLast = $left - $part <= 0.005;
                $originalPart = $originalAmount === null ? null : ($isLast ? max(0, $originalLeft) : round($originalAmount * $part / $amount, 2));

                $transfers->push(PayAgentMoneyRequest::recordTransfer($depositRequest, [
                    'date'                 => $modelData['date'],
                    'amount'               => $part,
                    'original_amount'      => $originalPart,
                    'original_currency_id' => Arr::get($modelData, 'original_currency_id'),
                    'reference'            => Arr::get($modelData, 'reference'),
                    'bank_transaction_id'  => Arr::get($modelData, 'bank_transaction_id'),
                    MatchAgentMoneyTransfers::LINE_KEY => Arr::get($modelData, MatchAgentMoneyTransfers::LINE_KEY),
                ], $media?->id, $userId));

                $left         = round($left - $part, 2);
                $originalLeft = $originalLeft === null ? null : round($originalLeft - $originalPart, 2);
            }

            return $transfers;
        });
    }

    /**
     * @return Builder<DepositRequest>
     */
    public static function approvedOpen(OrgAgent $orgAgent): Builder
    {
        return DepositRequest::where('agent_id', $orgAgent->agent_id)
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('state', DepositRequestStateEnum::REQUESTED)
            ->whereNotNull('approved_at')
            ->orderBy('requested_at')
            ->orderBy('id');
    }

    public function rules(): array
    {
        return [
            'date'            => ['required', 'date', 'before_or_equal:today'],
            'amount'          => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'original_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'receipt'         => [$this->asAction ? 'nullable' : 'required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $due = self::approvedOpen($this->orgAgent)->get()->sum(fn (DepositRequest $depositRequest) => max(0, PayAgentMoneyRequest::outstanding($depositRequest)));
        if ($due <= 0.005) {
            $validator->errors()->add('amount', __('Nothing approved is waiting to be paid.'));
        } elseif ((float) $this->get('amount') > $due + 0.005) {
            $validator->errors()->add('amount', __('That is more than is still due on the approved requests.'));
        }
    }

    /**
     * Paying for several requests at once needs the right to pay each one of them.
     */
    public static function canPayAll(OrgAgent $orgAgent, ?User $user): bool
    {
        $requests = self::approvedOpen($orgAgent)->with('agent')->get();

        return $requests->isNotEmpty() && $requests->every(fn (DepositRequest $depositRequest) => ApproveAgentMoneyRequest::canPay($depositRequest, $user));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canPayAll($this->orgAgent, $request->user());
    }

    public function asController(OrgAgent $orgAgent, ActionRequest $request): Collection
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($orgAgent->organisation, $request);

        $transfers = $this->handle($orgAgent, $this->validatedData);

        foreach ($transfers as $transfer) {
            NotifyMoneyRequestPaid::run($transfer->depositRequest, $transfer, $request->user());
        }

        return $transfers;
    }

    /**
     * @param  array{date: string, amount: float|string, original_amount?: float|string|null}  $modelData
     *
     * @return Collection<int, AgentPayment>
     */
    public function action(OrgAgent $orgAgent, array $modelData): Collection
    {
        $this->asAction = true;
        $this->orgAgent = $orgAgent;
        $this->initialisation($orgAgent->organisation, $modelData);

        return $this->handle($orgAgent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
