<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * HELP-4556: bottles filled from a bulk oil when a customer orders them. One artefact per size
 * (settings.fills_size = "10ml") fills every product coded "{its SKO code}-{size}", so a new oil
 * needs no setup. The oil still leaves the bulk SKO at picking; the whole order waits until filled.
 */
class FillToOrder
{
    use AsObject;

    public function artefactFor(mixed $product): ?Artefact
    {
        if (!$product instanceof Product || $product->orgStocks->count() !== 1) {
            return null;
        }

        $prefix = $product->orgStocks->first()->code.'-';
        if (!Str::startsWith(mb_strtolower($product->code), mb_strtolower($prefix))) {
            return null;
        }

        return Artefact::where('organisation_id', $product->organisation_id)
            ->whereRaw("lower(settings->>'fills_size') = ?", [mb_strtolower(Str::after(mb_strtolower($product->code), mb_strtolower($prefix)))])
            ->whereIn('state', ArtefactStateEnum::launched())
            ->first();
    }

    /** @return Collection<int, PartnerShoppingListItem> */
    public function pendingLines(Order $order): Collection
    {
        return PartnerShoppingListItem::whereIn('transaction_id', $order->transactions()->select('id'))
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->with('transaction.model')
            ->get()
            ->filter(fn (PartnerShoppingListItem $item) => $this->artefactFor($item->transaction?->model) !== null)
            ->values();
    }

    /** The order a fill line is filled for, read through its To Produce line so carried and split lines find it too */
    public function orderFor(JobOrderItem $item): ?Order
    {
        $lineId = Arr::get($item->data, 'fill.to_produce_id');

        return $lineId ? PartnerShoppingListItem::find($lineId)?->transaction?->order : null;
    }

    /** "EZC-20219 Eazycolours · Michelle Ash": the label to print depends on the shop (HELP-4638) */
    public function orderLabel(?Order $order): ?string
    {
        return $order ? $order->reference.' '.$order->shop->name.' · '.$order->customer?->name : null;
    }

    /**
     * One packing slip per order the job fills, so the bottles wait with their order (HELP-4638).
     *
     * @return Collection<int, array{order: Order, lines: Collection<int, array{code: string, name: string, quantity: float}>}>
     */
    public function slips(JobOrder $jobOrder): Collection
    {
        return $jobOrder->jobOrderItems()->orderBy('id')->get()
            ->map(function (JobOrderItem $item) {
                $transaction = PartnerShoppingListItem::find(Arr::get($item->data, 'fill.to_produce_id'))?->transaction;

                return $transaction?->order ? ['transaction' => $transaction, 'quantity' => (float) $item->quantity] : null;
            })
            ->filter()
            ->groupBy(fn (array $row) => $row['transaction']->order_id)
            ->map(fn (Collection $rows) => [
                'order' => $rows->first()['transaction']->order,
                'lines' => $rows->groupBy(fn (array $row) => $row['transaction']->id)
                    ->map(fn (Collection $sameLine) => [
                        'code'     => $sameLine->first()['transaction']->model?->code ?? '',
                        'name'     => $sameLine->first()['transaction']->model?->name ?? '',
                        'quantity' => $sameLine->sum('quantity'),
                    ])
                    ->values(),
            ])
            ->values();
    }

    /** Bottles filled for an order are on their way to the customer: taking the work back would leave the order picked without them */
    public function refuseAfterRelease(?JobOrderItem $item): void
    {
        $lineId = Arr::get($item?->data, 'fill.to_produce_id');
        if ($lineId && PartnerShoppingListItem::where('id', $lineId)->where('dismiss_reason', 'filled')->exists()) {
            throw ValidationException::withMessages([
                'session' => __('These bottles were filled for order :order and it has gone to the warehouse, they can not be taken back', ['order' => Arr::get($item->data, 'fill.order')]),
            ]);
        }
    }

    /** A customer's change to an order line follows to its fill line until the bottles are on a job */
    public function followOrderLine(Transaction $transaction, float $quantity): void
    {
        if (!$this->artefactFor($transaction->model)) {
            return;
        }

        $lines = PartnerShoppingListItem::where('transaction_id', $transaction->id)
            ->where(fn ($query) => $query->where('state', ShoppingListItemStateEnum::OPEN)->orWhere('dismiss_reason', 'filled'))
            ->get();
        $open = $lines->first(fn (PartnerShoppingListItem $line) => $line->state == ShoppingListItemStateEnum::OPEN && !$line->job_order_id);

        if ($quantity <= 0) {
            $lines->where('state', ShoppingListItemStateEnum::OPEN)->each(fn (PartnerShoppingListItem $line) => $line->update(['state' => ShoppingListItemStateEnum::DISMISSED, 'dismiss_reason' => 'order line removed', 'dismissed_at' => now()]));

            return;
        }

        $quantityOnOtherLines = (float) $lines->reject(fn (PartnerShoppingListItem $line) => $line->is($open))->sum('quantity');
        $openQuantity         = round($quantity - $quantityOnOtherLines, 3);
        if ($open) {
            $open->update($openQuantity > 0
                ? ['quantity' => $openQuantity]
                : ['state' => ShoppingListItemStateEnum::DISMISSED, 'dismiss_reason' => 'order line lowered', 'dismissed_at' => now()]);
        } elseif ($openQuantity > 0) {
            $first = $lines->first();
            if ($first) {
                PartnerShoppingListItem::create([
                    'group_id'        => $first->group_id,
                    'organisation_id' => $first->organisation_id,
                    'stock_id'        => $first->stock_id,
                    'org_stock_id'    => $first->org_stock_id,
                    'quantity'        => $openQuantity,
                    'transaction_id'  => $transaction->id,
                ]);
            }
        }
    }

    /** A cancelled order needs no bottles: its fill lines leave To Produce, and a job already filling them finishes without an order to send */
    public function dismissForCancelledOrder(Order $order): void
    {
        $this->pendingLines($order)->each(fn (PartnerShoppingListItem $line) => $line->update([
            'state'          => ShoppingListItemStateEnum::DISMISSED,
            'dismiss_reason' => 'order cancelled',
            'dismissed_at'   => now(),
        ]));
    }

    /** A filled line goes straight to its order: nothing enters stock, the order is released once nothing is left to fill */
    public function release(JobOrderItem $item): void
    {
        $item->refresh();
        $fill = Arr::get($item->data, 'fill');
        if (!$fill || !$item->isFinished() || (float) $item->quantity_received >= (float) $item->quantity) {
            return;
        }

        $item->update(['quantity_received' => $item->quantity]);

        $line = Arr::has($item->data, 'carried_to') ? null : PartnerShoppingListItem::find($fill['to_produce_id']);
        $line?->update(['state' => ShoppingListItemStateEnum::DISMISSED, 'dismiss_reason' => 'filled', 'dismissed_at' => now()]);

        $jobOrder = $item->jobOrder;
        if ($jobOrder->state == JobOrderStateEnum::CONFIRMED
            && $jobOrder->jobOrderItems()->with('tasks')->get()->every(fn (JobOrderItem $jobItem) => $jobItem->isFinished() && (float) $jobItem->quantity_received >= (float) $jobItem->quantity)) {
            $jobOrder->update(['state' => JobOrderStateEnum::RECEIVED, 'received_at' => now()]);
        }

        $order       = $line?->transaction?->order;
        $transaction = $line?->transaction;
        if ($transaction && !PartnerShoppingListItem::where('transaction_id', $transaction->id)->where('state', ShoppingListItemStateEnum::OPEN)->exists()) {
            $lineIds     = PartnerShoppingListItem::where('transaction_id', $transaction->id)->pluck('id')->all();
            $filledItems = JobOrderItem::whereIn('data->fill->to_produce_id', $lineIds)->where('quantity_received', '>', 0)->with('jobOrder')->get();
            $filled      = (int) $filledItems->sum('quantity_received');
            $product     = $transaction->model?->code;
            $order->update(['private_warehouse_note' => collect([
                $order->private_warehouse_note,
                $filled > 0 ? __(':product ×:filled filled on :job_orders, take them from the waiting area', [
                    'product'    => $product,
                    'filled'     => $filled,
                    'job_orders' => $filledItems->pluck('jobOrder.reference')->unique()->implode(', '),
                ]) : null,
                $filled < (float) $transaction->quantity_ordered
                    ? __('only :filled of :ordered :product filled', ['filled' => $filled, 'ordered' => (float) $transaction->quantity_ordered, 'product' => $product])
                    : null,
            ])->filter()->implode(' — ')]);
        }

        if ($order
            && $order->state == OrderStateEnum::SUBMITTED
            && ($order->pay_status == OrderPayStatusEnum::PAID || $order->to_be_paid_by == OrderToBePaidByEnum::CASH_ON_DELIVERY)
            && $this->pendingLines($order)->isEmpty()) {
            DB::afterCommit(fn () => SendOrderToWarehouse::make()->action($order->refresh(), []));
        }
    }
}
