<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\DepositRequest\DepositRequestStateEnum;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The client records the transfer it sent for an agent's money request: the money lands in its wallet with the
 * agent as a transfer received, and the request is settled once its transfers cover it.
 */
class PayAgentMoneyRequest extends OrgAction
{
    private DepositRequest $depositRequest;

    /**
     * @param  array{date: string, amount: float|string, original_amount?: float|string|null, reference?: string|null, receipt?: UploadedFile|null}  $modelData
     */
    public function handle(DepositRequest $depositRequest, array $modelData): AgentPayment
    {
        $receipt = Arr::get($modelData, 'receipt');
        $media   = $receipt ? self::saveBankStatement($depositRequest->agent, $receipt) : null;
        $userId = $this->asAction ? null : request()->user()?->id;

        return DB::transaction(function () use ($depositRequest, $modelData, $media, $userId) {
            $depositRequest = DepositRequest::whereKey($depositRequest->id)->lockForUpdate()->firstOrFail();

            return self::recordTransfer($depositRequest, $modelData, $media?->id, $userId);
        });
    }

    /**
     * Records one transfer against a request the caller has already locked, and settles the request once its transfers cover it.
     *
     * @param  array{date: string, amount: float|string, original_amount?: float|string|null, original_currency_id?: int|null, reference?: string|null, bank_transaction_id?: int|null, bank_line_key?: string|null}  $modelData
     */
    public static function recordTransfer(DepositRequest $depositRequest, array $modelData, ?int $mediaId, ?int $userId): AgentPayment
    {
        if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
            throw ValidationException::withMessages(['request' => __('This request is no longer open.')]);
        }
        if (!$depositRequest->isApproved()) {
            throw ValidationException::withMessages(['request' => __('Approve the request before paying it.')]);
        }
        if ((float) $modelData['amount'] > self::outstanding($depositRequest) + 0.005) {
            throw ValidationException::withMessages(['amount' => __('That is more than is still due on this request.')]);
        }

        $client         = $depositRequest->organisation;
        $originalAmount = Arr::get($modelData, 'original_amount');

        $transfer = AgentPayment::create([
            'group_id'             => $depositRequest->group_id,
            'agent_id'             => $depositRequest->agent_id,
            'organisation_id'      => $client->id,
            'deposit_request_id'   => $depositRequest->id,
            'type'                 => AgentPaymentTypeEnum::TRANSFER_RECEIVED,
            'date'                 => $modelData['date'],
            'amount'               => round((float) $modelData['amount'], 2),
            'currency_id'          => $depositRequest->currency_id,
            'original_amount'      => $originalAmount ? round((float) $originalAmount, 2) : null,
            'original_currency_id' => $originalAmount ? (Arr::get($modelData, 'original_currency_id') ?? $client->currency_id) : null,
            'reference'            => Arr::get($modelData, 'reference') ?: $depositRequest->reference,
            'notes'                => __('Money request :reference', ['reference' => $depositRequest->reference ?? '#'.$depositRequest->id]),
            'media_id'             => $mediaId,
            'bank_transaction_id'  => Arr::get($modelData, 'bank_transaction_id'),
            'created_by'           => $userId,
            'data'                 => array_filter(['recorded_by' => AgentPayment::RECORDED_BY_CLIENT, MatchAgentMoneyTransfers::LINE_KEY => Arr::get($modelData, MatchAgentMoneyTransfers::LINE_KEY)]),
        ]);

        if ((float) $depositRequest->transfers()->sum('amount') >= (float) $depositRequest->items()->sum('amount') - 0.005) {
            $depositRequest->update(['state' => DepositRequestStateEnum::SETTLED, 'settled_at' => now()]);
            $depositRequest->items()->whereNull('paid_at')->update(['paid_at' => now()]);
        }

        return $transfer;
    }

    public static function saveBankStatement(Agent $agent, UploadedFile $receipt): Media
    {
        return SaveModelAttachment::make()->action($agent, [
            'path'         => $receipt->getPathName(),
            'originalName' => $receipt->getClientOriginalName(),
            'extension'    => $receipt->getClientOriginalExtension(),
            'scope'        => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT->value,
            'caption'      => pathinfo($receipt->getClientOriginalName(), PATHINFO_FILENAME),
        ]);
    }

    public function rules(): array
    {
        return [
            'date'            => ['required', 'date', 'before_or_equal:today'],
            'amount'          => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'original_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'receipt'         => [$this->asAction ? 'nullable' : 'required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
            $validator->errors()->add('request', __('This request is no longer open.'));
        } elseif (!$this->depositRequest->isApproved()) {
            $validator->errors()->add('request', __('Approve the request before paying it.'));
        } elseif ((float) $this->get('amount') > self::outstanding($this->depositRequest) + 0.005) {
            $validator->errors()->add('amount', __('That is more than is still due on this request.'));
        }
    }

    public static function outstanding(DepositRequest $depositRequest): float
    {
        return round((float) $depositRequest->items()->sum('amount') - (float) $depositRequest->transfers()->sum('amount'), 2);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ApproveAgentMoneyRequest::canPay($this->depositRequest, $request->user());
    }

    public function asController(DepositRequest $depositRequest, ActionRequest $request): AgentPayment
    {
        $this->depositRequest = $depositRequest;
        abort_unless($depositRequest->organisation, 404);
        $this->initialisation($depositRequest->organisation, $request);

        $transfer = $this->handle($depositRequest, $this->validatedData);

        NotifyMoneyRequestPaid::run($depositRequest, $transfer, $request->user());

        return $transfer;
    }

    /**
     * @param  array{date: string, amount: float|string}  $modelData
     */
    public function action(DepositRequest $depositRequest, array $modelData): AgentPayment
    {
        $this->asAction       = true;
        $this->depositRequest = $depositRequest;
        $this->initialisation($depositRequest->organisation, $modelData);

        return $this->handle($depositRequest, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
