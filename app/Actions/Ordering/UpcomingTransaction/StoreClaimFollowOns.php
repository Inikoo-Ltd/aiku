<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UpcomingTransaction;

use App\Actions\CRM\CustomerClaim\RecordStaffClaim;
use App\Actions\Accounting\Invoice\RefundClaimToBalance;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\CheckClaimCompensation;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Order\SaveOrderModification;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Models\SysAdmin\User;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dispatching\DeliveryNote;
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
 * HELP-4902: when the customer already has an order on its way, the follow-ons can go on that order
 * at once ($sendWith) as free lines on its delivery note, so they are picked and packed with it and
 * keep that order's place in the queue instead of jumping it as a priority replacement.
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
            'send_with_order_id'             => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: float|int}>  $claimedItems
     * @return Collection<int, UpcomingTransaction>
     */
    public function handle(Order $order, array $claimedItems, ?string $reason = null, ?string $privateNotes = null, ?Order $sendWith = null, ?User $user = null): Collection
    {
        if ($order->customer_client_id) {
            throw ValidationException::withMessages(['delivery_note_items' => __('Only orders the customer receives themselves can be replaced in their next order')]);
        }

        if ($sendWith && !in_array($sendWith->id, self::ordersToSendWith($order)->pluck('id')->all())) {
            throw ValidationException::withMessages(['send_with_order_id' => __('The replacement can only go with an open order of this customer that the warehouse has not finished picking')]);
        }

        $shares = RefundClaimToBalance::sharesByTransaction($order, $claimedItems);
        CheckClaimCompensation::ensure($order, RefundClaimToBalance::sharesByTransaction($order, $claimedItems, false));

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

        $dispatched = CheckClaimCompensation::dispatched($order);

        return DB::transaction(function () use ($transactions, $order, $dispatched, $shares, $publicNotes, $privateNotes, $sendWith, $user) {
            $deliveryNote = $sendWith ? SaveOrderModification::make()->lockDeliveryNoteAcceptingNewLines($sendWith) : null;

            $followOns = $transactions->map(fn (Transaction $transaction) => StoreUpcomingTransaction::make()->action($order->customer, [
                'product_id'    => $transaction->model_id,
                'quantity'      => max(1, ceil(($dispatched[$transaction->id] ?? 0) * $shares[$transaction->id])),
                'type'          => UpcomingTransactionTypeEnum::FOLLOW_ON,
                'source_transaction_id' => $transaction->id,
                'public_notes'  => $publicNotes,
                'private_notes' => $privateNotes,
            ]))->values();

            if ($sendWith) {
                $followOns->each(fn (UpcomingTransaction $followOn) => $this->addToOrder($followOn, $sendWith, $deliveryNote, $user));
                $sendWith->update(['post_submit_modification_data' => [
                    ...($sendWith->post_submit_modification_data ?? []),
                    [
                        'date_time'   => now()->toDateTimeString(),
                        'modified_by' => $user?->username,
                        'data'        => ['claim_of' => $order->reference, 'follow_ons' => $followOns->map(fn (UpcomingTransaction $followOn) => $followOn->product?->code.' x'.(float) $followOn->quantity)->all()],
                    ],
                ]]);
            }

            return $followOns;
        });
    }

    /**
     * The customer's other orders that can still take a line, newest first.
     *
     * @return Collection<int, Order>
     */
    public static function ordersToSendWith(Order $order): Collection
    {
        return Order::query()
            ->where('customer_id', $order->customer_id)
            ->whereNull('customer_client_id')
            ->whereNot('id', $order->id)
            ->whereIn('state', [OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE, OrderStateEnum::HANDLING, OrderStateEnum::HANDLING_BLOCKED, OrderStateEnum::PICKED])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Order $candidate) => SaveOrderModification::acceptsNewProducts($candidate))
            ->values();
    }

    private function addToOrder(UpcomingTransaction $followOn, Order $sendWith, ?DeliveryNote $deliveryNote, ?User $user): void
    {
        $product = $followOn->product()->with('orgStocks', 'currentHistoricProduct')->first();
        if (!$product?->currentHistoricProduct) {
            throw ValidationException::withMessages(['delivery_note_items' => __('Product :code can no longer be sent', ['code' => $product?->code])]);
        }

        /** A free line leaves the order's amounts as they are: no totals recalculation, which on a submitted order would reprice its shipping and discounts on the extra weight */
        $transaction = StoreTransaction::make()->action(
            order: $sendWith,
            historicAsset: $product->currentHistoricProduct,
            modelData: [
                'quantity_ordered' => 0,
                'quantity_bonus'   => $followOn->quantity,
                'is_gift'          => true,
                'is_follow_on'     => true,
            ],
            strict: false
        );
        OrderHydrateTransactions::dispatch($sendWith);

        UpdateUpcomingTransaction::run($followOn, [
            'order_id'       => $sendWith->id,
            'transaction_id' => $transaction->id,
            'state'          => UpcomingTransactionStateEnum::APPLIED,
        ]);

        if ($deliveryNote) {
            SaveOrderModification::make()->addLineToDeliveryNote($deliveryNote, $transaction, $product, $user);
        }
    }

    public function asController(Order $order, ActionRequest $request): Collection
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        $items     = $this->validatedData['delivery_note_items'];
        $sendWith  = isset($this->validatedData['send_with_order_id']) ? Order::where('customer_id', $order->customer_id)->find($this->validatedData['send_with_order_id']) : null;
        if (isset($this->validatedData['send_with_order_id']) && !$sendWith) {
            throw ValidationException::withMessages(['send_with_order_id' => __('Order not found')]);
        }
        $value     = RecordStaffClaim::itemsValue($order, $items);
        $followOns = $this->handle($order, $items, $this->validatedData['reason'] ?? null, $this->validatedData['private_notes'] ?? null, $sendWith, $request->user());
        $details   = ['in_next_order' => $followOns->map(fn ($followOn) => $followOn->product?->code.' x'.(float) $followOn->quantity)->all()];
        if ($sendWith) {
            $details['sent_with'] = $sendWith->reference;
        }
        RecordStaffClaim::make()->fromItems($order, 'next_order', $items, $details, $request->user(), $value, $this->validatedData['reason'] ?? null);

        return $followOns;
    }

    public function jsonResponse(Collection $followOns): JsonResponse
    {
        return response()->json(['count' => $followOns->count()]);
    }
}
