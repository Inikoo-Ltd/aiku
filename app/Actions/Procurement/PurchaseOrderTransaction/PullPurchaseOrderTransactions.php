<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-13 08:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * An order through an agent takes all or part of items of another open order of the same agent, of its own supplier or
 * of another one: each item is moved into it, the units it takes leave the other order and can be given back with undo.
 * A line that can not move stops them all.
 */
class PullPurchaseOrderTransactions extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{source_purchase_order_id: int, lines: array<int, array{id: int, quantity: float|string, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null}>} $modelData
     *
     * @return array<int, array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, source_cancelled: bool, source_empty: bool, deposit_paid: bool}>
     */
    public function handle(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): array
    {
        return DB::transaction(function () use ($purchaseOrder, $modelData, $user) {
            $sourcePurchaseOrder = MovePurchaseOrderTransactionToSupplier::purchaseOrdersToPullFromQuery($purchaseOrder)->find($modelData['source_purchase_order_id']);

            if (!$sourcePurchaseOrder) {
                throw ValidationException::withMessages(['source_purchase_order_id' => __('Choose an open order of the same agent in :currency', ['currency' => $purchaseOrder->currency?->code])]);
            }

            $lines        = $modelData['lines'];
            $transactions = $sourcePurchaseOrder->purchaseOrderTransactions()->whereIn('id', Arr::pluck($lines, 'id'))->get()->keyBy('id');

            if ($transactions->count() !== count($lines)) {
                throw ValidationException::withMessages(['lines' => __('Some items are no longer on :reference: reload them', ['reference' => $sourcePurchaseOrder->reference])]);
            }

            $acceptsSupplierPrice = (bool) $user?->authTo("procurement.$purchaseOrder->organisation_id.edit");
            $results              = [];

            foreach ($lines as $line) {
                $transaction = $transactions->get($line['id']);

                try {
                    $results[] = MovePurchaseOrderTransactionToSupplier::make()->handle(
                        $transaction,
                        Arr::only($line, ['quantity', 'code', 'name', 'cost', 'units_per_carton']),
                        $user,
                        into: $purchaseOrder,
                        acceptsSupplierPrice: $acceptsSupplierPrice
                    );
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => [
                        in_array($field, ['source_purchase_order_id', 'purchase_order'], true) ? $field : "lines.$transaction->id.$field" => $messages,
                    ])->all());
                }
            }

            return $results;
        });
    }

    public function rules(): array
    {
        return [
            'source_purchase_order_id' => ['required', 'integer'],
            'lines'                    => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.id'               => ['required', 'integer', 'distinct'],
            'lines.*.quantity'         => ['required', 'numeric', 'gt:0'],
            'lines.*.code'             => ['sometimes', 'nullable', 'string', 'max:64'],
            'lines.*.name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'lines.*.cost'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.units_per_carton' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $results = $this->handle($purchaseOrder, $this->validatedData, $request->user());
        self::notifyBuyer($purchaseOrder, $request->user(), $results);

        return back();
    }

    /**
     * @param array<int, array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, source_cancelled: bool, source_empty: bool, deposit_paid: bool}> $results
     */
    public static function notifyBuyer(PurchaseOrder $purchaseOrder, User $user, array $results): void
    {
        $lastResult = end($results);
        $source     = $lastResult['source']->purchaseOrder;
        $lines      = collect($results)->map(fn (array $result) => __(':quantity units of :code', [
            'quantity' => trimDecimalZeros($result['moved']->quantity_ordered),
            'code'     => $result['moved']->supplierProduct?->code ?? $result['moved']->orgStock?->code,
        ]))->implode(', ');

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $user, fn () => implode(' ', array_filter([
            __('the agent moved :lines here from :reference.', ['lines' => $lines, 'reference' => $source->reference]),
            ...collect($results)->filter(fn (array $result) => $result['moved']->price_query_state)->map(fn (array $result) => __('New supplier price of :code :new, was :old: answer it on this order.', [
                'code' => $result['moved']->supplierProduct?->code ?? $result['moved']->orgStock?->code,
                'new'  => trimDecimalZeros($result['moved']->price_query_unit_cost).' '.$purchaseOrder->currency?->code,
                'old'  => trimDecimalZeros($result['moved']->price_query_previous_unit_cost).' '.$purchaseOrder->currency?->code,
            ]))->values()->all(),
            $lastResult['source_cancelled'] ? __(':reference had nothing left and was cancelled.', ['reference' => $source->reference]) : null,
            $lastResult['source_empty'] && $lastResult['deposit_paid']
                ? __(':reference has nothing left but a deposit was paid on it: recover it before cancelling it.', ['reference' => $source->reference]) : null,
        ])));
    }
}
