<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 06:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class ConfirmPurchaseOrderPayment extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    /**
     * The organisation's purchaser or accountant, or the staff of the agent the order was placed through.
     */
    public static function canConfirm(PurchaseOrder $purchaseOrder, User $user): bool
    {
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $user->authTo(array_filter([
            "procurement.$purchaseOrder->organisation_id.edit",
            "accounting.$purchaseOrder->organisation_id.edit",
            $agentOrganisationId ? "procurement.$agentOrganisationId.edit" : null,
        ]));
    }

    /**
     * The first payment is the deposit; the rest is the balance, released by the QC approval.
     */
    public static function balanceAwaitsQc(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->qc_passed_at === null && SyncPurchaseOrderDepositPaid::paidAmount($purchaseOrder) > 0;
    }

    public static function acceptsPayments(PurchaseOrder $purchaseOrder): bool
    {
        return !in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED], true);
    }

    /**
     * The currencies a payment can be sent in: the purchase order's, then the organisation's, then US dollars and euros.
     *
     * @return \Illuminate\Support\Collection<int, Currency>
     */
    public static function paymentCurrencies(PurchaseOrder $purchaseOrder): Collection
    {
        return collect([$purchaseOrder->currency, $purchaseOrder->organisation->currency])
            ->merge(Currency::whereIn('code', ['USD', 'EUR'])->orderByDesc('code')->get())
            ->filter()->unique('id')->values();
    }

    /**
     * Records one deposit: a payment to the supplier towards the price of the products of the purchase order, without
     * its extra costs, shipping and duties. Deposits add up until nothing is left to pay. The amount is always in the
     * order's currency; money sent in another currency is kept beside it as the original amount.
     *
     * @param  array{ paid_at: string, amount: float|string, method: string, reference?: string|null, bank_statement?: UploadedFile|null, user_id?: int|null, reason?: string|null, original_amount?: float|string|null, original_currency_id?: int|null}  $modelData
     */
    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderDepositPayment
    {
        $file  = Arr::get($modelData, 'bank_statement');
        $media = $file ? SaveModelAttachment::make()->action($purchaseOrder, [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'scope'        => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT->value,
            'sub_scope'    => 'deposit',
            'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]) : null;

        $isBalance = SyncPurchaseOrderDepositPaid::paidAmount($purchaseOrder) > 0;

        $originalCurrencyId = Arr::get($modelData, 'original_currency_id');
        $originalAmount     = $originalCurrencyId && (int) $originalCurrencyId !== $purchaseOrder->currency_id ? Arr::get($modelData, 'original_amount') : null;

        $payment = $purchaseOrder->depositPayments()->create([
            'group_id'        => $purchaseOrder->group_id,
            'organisation_id' => $purchaseOrder->organisation_id,
            'currency_id'     => $purchaseOrder->currency_id,
            'date'            => $modelData['paid_at'],
            'amount'          => round((float) $modelData['amount'], 2),
            'original_amount'      => $originalAmount ? round((float) $originalAmount, 2) : null,
            'original_currency_id' => $originalAmount ? (int) $originalCurrencyId : null,
            'method'          => $modelData['method'],
            'reference'       => Arr::get($modelData, 'reference'),
            'media_id'        => $media?->id,
            'created_by'      => Arr::get($modelData, 'user_id'),
        ]);

        SyncPurchaseOrderDepositPaid::run($purchaseOrder);

        if ($reason = Arr::get($modelData, 'reason')) {
            MarkPurchaseOrderStage::auditOverride($purchaseOrder, $isBalance ? 'balance_paid_at' : 'deposit_paid_at', null, $payment->date->toDateString(), $reason);
        }

        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || self::canConfirm($this->purchaseOrder, $request->user());
    }

    public function rules(): array
    {
        return [
            'paid_at'        => ['required', 'date', 'before_or_equal:tomorrow'],
            'amount'         => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method'         => ['required', Rule::enum(PurchaseOrderPaymentMethodEnum::class)],
            'reference'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_statement' => ['required_unless:method,cash', 'nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
            'user_id'        => ['sometimes', 'nullable', 'integer'],
            'reason'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'original_currency_id' => ['sometimes', 'nullable', 'integer', Rule::in(self::paymentCurrencies($this->purchaseOrder)->pluck('id')->all())],
            'original_amount'      => ['nullable', 'numeric', 'gt:0', 'max:999999999999', Rule::requiredIf(fn () => $this->get('original_currency_id') && (int) $this->get('original_currency_id') !== $this->purchaseOrder->currency_id)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!self::acceptsPayments($this->purchaseOrder)) {
            $validator->errors()->add('paid_at', __('A payment can only be confirmed once the purchase order is submitted'));
        } elseif (($left = SyncPurchaseOrderDepositPaid::leftToPay($this->purchaseOrder)) <= 0) {
            $validator->errors()->add('paid_at', __('Nothing is left to pay on this purchase order'));
        } elseif ($validator->errors()->isEmpty() && round((float) $this->get('amount'), 2) > $left) {
            $validator->errors()->add('amount', __('The payment cannot be more than what is left to pay: :amount', ['amount' => number_format($left, 2)]));
        } elseif (!$this->asAction && $validator->errors()->isEmpty() && !trim((string) $this->get('reason'))) {
            $reasons = MarkPurchaseOrderStage::make()->reasonsNeeded($this->purchaseOrder, 'deposit_paid_at', $this->get('paid_at'));
            if (self::balanceAwaitsQc($this->purchaseOrder)) {
                $reasons[] = __('the balance is paid before the QC is approved');
            }
            if ($reasons) {
                $validator->errors()->add('reason', __('Give a reason: :reasons', ['reasons' => implode('; ', $reasons)]));
            }
        }
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrderDepositPayment
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, [...$this->validatedData, 'user_id' => $request->user()->id]);
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderDepositPayment
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $modelData);

        return $this->handle($purchaseOrder, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
