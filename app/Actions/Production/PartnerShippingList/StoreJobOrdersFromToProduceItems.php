<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\StoreJobOrdersGroupedByArtisan;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreJobOrdersFromToProduceItems extends OrgAction
{
    /**
     * @param  array<int, int>  $ids
     * @return array{job_orders: array<int, JobOrder>, skipped: array<int, array{id: int, reason: string}>}
     */
    public function handle(Production $production, array $ids, ?int $employeeId = null, float|int|null $quantity = null): array
    {
        $seller = $production->organisation;

        $items = PartnerShoppingListItem::query()
            ->whereIn('id', $ids)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('job_order_id')
            ->where(function ($query) use ($seller) {
                $query->where('partner_organisation_id', $seller->id)
                    ->orWhere(function ($query) use ($seller) {
                        $query->whereNull('partner_organisation_id')->where('organisation_id', $seller->id);
                    });
            })
            ->get();

        $skipped = [];
        $lines   = [];

        foreach ($items as $item) {
            $artefact = $this->resolveArtefact($production, $item);
            if (!$artefact) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no artefact in this factory for this stock'];
                continue;
            }

            $lines[] = [
                'artefact' => $artefact,
                'quantity' => $quantity && count($ids) === 1 ? $quantity : ($item->quantity_to_produce ?? $item->quantity),
                'after'    => fn (JobOrder $jobOrder) => $item->update(['job_order_id' => $jobOrder->id]),
            ];
        }

        $jobOrders = StoreJobOrdersGroupedByArtisan::run($production, $lines, $employeeId);

        return ['job_orders' => $jobOrders, 'skipped' => $skipped];
    }

    private function resolveArtefact(Production $production, PartnerShoppingListItem $item): ?Artefact
    {
        $orgStockId = OrgStock::where('organisation_id', $production->organisation_id)
            ->where('stock_id', $item->stock_id)
            ->value('id');
        if (!$orgStockId) {
            return null;
        }

        return Artefact::where('production_id', $production->id)
            ->where('org_stock_id', $orgStockId)
            ->first();
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'quantity'    => ['sometimes', 'nullable', 'numeric', 'min:1'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(Production $production, array $ids): array
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, ['ids' => $ids]);

        return $this->handle($production, $ids);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['ids'], $this->validatedData['employee_id'] ?? null, $this->validatedData['quantity'] ?? null);
    }

    public function htmlResponse(array $result): RedirectResponse
    {
        $count = count($result['job_orders']);
        $skipped = count($result['skipped']);

        return Redirect::back()->with('notification', [
            'status' => $skipped ? 'warning' : 'success',
            'title'  => trans_choice(':count job order created|:count job orders created', $count, ['count' => $count])
                .($skipped ? ', '.trans_choice(':count line skipped, no artefact|:count lines skipped, no artefact', $skipped, ['count' => $skipped]) : ''),
        ]);
    }
}
