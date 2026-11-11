<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-11 13:37
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
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
 * Several lines of an order through an agent moved together to one supplier order: the first line picks or opens the
 * order (typed reference, the supplier's order of the same agent order, or a new one) and the others follow it.
 * A line that can not move stops them all.
 */
class MovePurchaseOrderTransactionsToSupplier extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;

    /**
     * @param array{org_supplier_id: int, reference?: string|null, lines: array<int, array{id: int, quantity: float|string, code?: string|null, name?: string|null, cost?: float|string|null, units_per_carton?: float|string|null}>} $modelData
     *
     * @return array<int, array{source: PurchaseOrderTransaction, moved: PurchaseOrderTransaction, source_cancelled: bool, source_empty: bool, deposit_paid: bool}>
     */
    public function handle(PurchaseOrder $purchaseOrder, array $modelData, ?User $user = null): array
    {
        return DB::transaction(function () use ($purchaseOrder, $modelData, $user) {
            $lines        = $modelData['lines'];
            $transactions = $purchaseOrder->purchaseOrderTransactions()->whereIn('id', Arr::pluck($lines, 'id'))->get()->keyBy('id');

            if ($transactions->count() !== count($lines)) {
                throw ValidationException::withMessages(['lines' => __('Some items are no longer on this order: reload the page')]);
            }

            $reference = Arr::get($modelData, 'reference');
            $results   = [];

            foreach ($lines as $line) {
                $transaction = $transactions->get($line['id']);

                try {
                    $result = MovePurchaseOrderTransactionToSupplier::make()->handle($transaction, array_merge(
                        Arr::only($line, ['quantity', 'code', 'name', 'cost', 'units_per_carton']),
                        ['org_supplier_id' => $modelData['org_supplier_id'], 'reference' => $reference]
                    ), $user);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => [
                        in_array($field, ['org_supplier_id', 'reference'], true) ? $field : "lines.$transaction->id.$field" => $messages,
                    ])->all());
                }

                $results[] = $result;
                $reference = $result['moved']->purchaseOrder->reference;
            }

            return $results;
        });
    }

    public function rules(): array
    {
        return [
            'org_supplier_id'          => ['required', 'integer'],
            'reference'                => ['sometimes', 'nullable', 'string', 'max:64'],
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
        MovePurchaseOrderTransactionToSupplier::notifyBuyer($purchaseOrder, $request->user(), $results);

        return back();
    }
}
