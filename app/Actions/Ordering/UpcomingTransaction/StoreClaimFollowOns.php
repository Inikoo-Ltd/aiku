<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction;

use App\Actions\Accounting\Invoice\RefundClaimToBalance;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Ordering\UpcomingTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The claim's lines go free into the customer's next order instead of a replacement shipped now
 * (HELP-3767): each becomes a follow-on, which the next order picks up at submit as a bonus line.
 * The claim counts in delivery note units, so each line asks for the share of its order line
 * that was claimed, rounded up to whole products.
 */
class StoreClaimFollowOns extends OrgAction
{
    private Order $order;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->order->shop_id}.edit");
    }

    public function rules(): array
    {
        return [
            'delivery_note_items'            => ['required', 'array', 'min:1'],
            'delivery_note_items.*.id'       => ['required', 'integer'],
            'delivery_note_items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'reason'                         => ['sometimes', 'nullable', Rule::enum(DeliveryNoteItemReplacementReasonEnum::class)],
            'private_notes'                  => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: float|int}>  $claimedItems
     * @return Collection<int, UpcomingTransaction>
     */
    public function handle(Order $order, array $claimedItems, ?string $reason = null, ?string $privateNotes = null): Collection
    {
        if ($order->customer_client_id) {
            throw ValidationException::withMessages(['delivery_note_items' => __('Only orders the customer receives themselves can be replaced in their next order')]);
        }

        $shares = RefundClaimToBalance::sharesByTransaction($order, $claimedItems);

        $transactions = Transaction::query()
            ->whereIn('id', array_keys($shares))
            ->where('order_id', $order->id)
            ->where('model_type', 'Product')
            ->get();

        if ($transactions->isEmpty()) {
            throw ValidationException::withMessages(['delivery_note_items' => __('These lines are not products of this order')]);
        }

        $reasonLabel  = $reason ? DeliveryNoteItemReplacementReasonEnum::from($reason)->label() : null;
        $publicNotes  = __('Replacement for :order', ['order' => $order->reference], $order->shop->language?->code);
        $privateNotes = collect([__('Claim of :order', ['order' => $order->reference]), $reasonLabel, $privateNotes])->filter()->implode(' · ');

        return DB::transaction(fn () => $transactions->map(fn (Transaction $transaction) => StoreUpcomingTransaction::make()->action($order->customer, [
            'product_id'    => $transaction->model_id,
            'quantity'      => max(1, ceil(((float) $transaction->quantity_ordered + (float) $transaction->quantity_bonus) * $shares[$transaction->id])),
            'type'          => UpcomingTransactionTypeEnum::FOLLOW_ON,
            'public_notes'  => $publicNotes,
            'private_notes' => $privateNotes,
        ]))->values());
    }

    public function asController(Order $order, ActionRequest $request): Collection
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData['delivery_note_items'], $this->validatedData['reason'] ?? null, $this->validatedData['private_notes'] ?? null);
    }

    public function jsonResponse(Collection $followOns): JsonResponse
    {
        return response()->json(['count' => $followOns->count()]);
    }
}
