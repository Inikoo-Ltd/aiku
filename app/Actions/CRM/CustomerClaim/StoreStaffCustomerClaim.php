<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * + Claim on an order, for staff (HELP-4869 step 2). Items outcomes (credit to balance, possibly a
 * part, replacement, free in the next order) are settled at once with the staff member as the
 * approver. An invoice refund opens a refund draft on the order's invoice, linked to the claim, and
 * the claim is settled when that refund is finalised (RecordStaffClaim::fromRefund).
 */
class StoreStaffCustomerClaim extends OrgAction
{
    public const array OUTCOMES = ['refund_to_balance', 'replacement', 'next_order', 'invoice_refund'];

    private Order $order;

    private ?CustomerClaim $claim = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(ShopPermissionsEnum::getPermissionName(PlanChatClaim::permission((string) $request->input('resolution'))->value, $this->order->shop));
    }

    public function rules(): array
    {
        return [
            'resolution'             => ['required', Rule::in(self::OUTCOMES)],
            'percentage'             => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'comment'                => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines'                  => ['required_unless:resolution,invoice_refund', 'array', 'max:80'],
            'lines.*.transaction_id' => ['required', 'integer'],
            'lines.*.quantity'       => ['required', 'numeric', 'gt:0'],
            'lines.*.reason'         => ['required', Rule::enum(DeliveryNoteItemReplacementReasonEnum::class)],
            'lines.*.photos'         => ['sometimes', 'array', 'max:'.GetCustomerClaimForm::MAX_PHOTOS_PER_LINE],
            'lines.*.photos.*'       => ['image', 'max:10240'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \Throwable
     */
    public function handle(Order $order, array $data, User $user): CustomerClaim
    {
        $customer = $order->customer;
        $form     = GetCustomerClaimForm::run($customer, $order);
        $lines    = collect($form['lines'] ?? [])->keyBy('transaction_id');
        $items    = collect($data['lines'] ?? [])->map(function (array $line, int $index) use ($lines) {
            $formLine = $lines->get((int) $line['transaction_id']);
            if (!$formLine || (float) $line['quantity'] > $formLine['claimable'] + 0.0005) {
                throw ValidationException::withMessages(["lines.$index" => __('This line cannot be claimed.')]);
            }

            return ['code' => $formLine['code'], 'quantity' => (float) $line['quantity'], 'reason' => $line['reason']];
        })->all();

        if ($data['resolution'] === 'invoice_refund') {
            return $this->invoiceRefundClaim($order, $items, $data);
        }

        if (!$form) {
            throw ValidationException::withMessages(['lines' => __('This order has nothing dispatched to claim on.')]);
        }

        $plan = PlanChatClaim::make()->planOrder($order, $data['resolution'], $items, null, isset($data['percentage']) ? (float) $data['percentage'] : null);
        if ($plan['error']) {
            throw ValidationException::withMessages(['lines' => $plan['error']]);
        }
        $value = PlanChatClaim::make()->planOrder($order, 'refund_to_balance', $items)['amount'];

        $claim = StoreCustomerClaim::make()->createRecord($customer, $order, $plan, $data, CustomerClaimSourceEnum::STAFF, null, $value);

        return SettleCustomerClaim::run($claim, $user);
    }

    /**
     * @param  array<int, array{code: string, quantity: float, reason: string}>  $items
     * @param  array<string, mixed>  $data
     */
    private function invoiceRefundClaim(Order $order, array $items, array $data): CustomerClaim
    {
        $invoice = $order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->where(fn ($query) => $query->where('in_process', false)->orWhereNull('in_process'))->latest('id')->first();
        if (!$invoice) {
            throw ValidationException::withMessages(['resolution' => __('This order has no invoice to refund.')]);
        }

        $plan  = $items ? PlanChatClaim::make()->planOrder($order, 'refund_to_balance', $items) : ['claimed' => [], 'amount' => 0.0, 'currency' => $order->currency?->code, 'error' => null];
        $plan['claimed'] = $plan['error'] ? [] : $plan['claimed'];
        $refund = StoreRefund::make()->action($invoice, []);

        $claim = StoreCustomerClaim::make()->createRecord($order->customer, $order, array_merge($plan, ['currency' => $plan['currency'] ?? $order->currency?->code]), array_merge($data, ['resolution' => 'invoice_refund']), CustomerClaimSourceEnum::STAFF, null, (float) ($plan['amount'] ?? 0), CustomerClaimKindEnum::REFUND);
        $claim->update(['refund_id' => $refund->id]);

        return $claim;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): CustomerClaim
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->claim = $this->handle($order, $this->validatedData, $request->user());
    }

    public function htmlResponse(CustomerClaim $claim): RedirectResponse
    {
        $parameters = [$claim->shop->organisation->slug, $claim->shop->slug];

        if ($claim->refund_id && $claim->refund) {
            return Redirect::route('grp.org.shops.show.ordering.orders.show.refunds.show', [...$parameters, $claim->order->slug, $claim->refund->slug]);
        }

        return Redirect::route('grp.org.shops.show.claims.show', [...$parameters, $claim->ulid]);
    }
}
