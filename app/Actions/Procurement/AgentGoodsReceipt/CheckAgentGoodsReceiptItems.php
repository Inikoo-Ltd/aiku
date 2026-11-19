<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-23 09:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt;

use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Actions\OrgAction;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent checks several goods receipt lines at once and finds no rejects in any of them. Each line goes through
 * the single check, all or none: a line that can no longer be checked stops the whole batch. Lines already checked
 * are left as they are, so a second click never overwrites rejects someone recorded.
 */
class CheckAgentGoodsReceiptItems extends OrgAction
{
    use WithAgentGoodsReceipt;

    /**
     * @param  array<int, int>  $itemIds
     */
    public function handle(Organisation $organisation, array $itemIds, ?User $user = null): int
    {
        $items = AgentGoodsReceiptItem::whereIn('id', $itemIds)
            ->whereHas('agentGoodsReceipt', fn ($query) => $query->where('agent_id', $organisation->agent?->id))
            ->with(['agentGoodsReceipt', 'agentWarehouseStock', 'supplierProduct:id,code'])
            ->get();

        abort_unless($items->count() === count(array_unique($itemIds)), 404);

        $unchecked = $items->reject(fn (AgentGoodsReceiptItem $item) => $item->isChecked());

        DB::transaction(function () use ($unchecked, $user) {
            foreach ($unchecked as $item) {
                try {
                    CheckAgentGoodsReceiptItem::make()->handle($item, ['quantity_rejected' => 0], $user, refreshesOrder: false);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'items' => $item->supplierProduct?->code.': '.collect($exception->errors())->flatten()->first(),
                    ]);
                }
            }
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds($unchecked->pluck('purchase_order_id'));

        return $unchecked->count();
    }

    public function rules(): array
    {
        return [
            'items'   => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['integer', 'distinct'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData['items'], $request->user());
    }

    public function htmlResponse(int $checked): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'  => 'success',
            'title'   => __(':count goods receipt lines checked', ['count' => $checked]),
            'message' => __('No rejects'),
        ]);
    }
}
