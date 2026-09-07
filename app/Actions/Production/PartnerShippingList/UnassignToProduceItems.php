<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UnassignToProduceItems extends OrgAction
{
    /**
     * @param  array<int, int>  $ids
     * @return array<int, PartnerShoppingListItem>
     */
    public function handle(Production $production, array $ids): array
    {
        $items = PartnerShoppingListItem::query()
            ->whereIn('id', $ids)
            ->whereNotNull('job_order_id')
            ->with('jobOrder')
            ->get()
            ->filter(fn (PartnerShoppingListItem $item) => in_array($item->jobOrder?->state, [JobOrderStateEnum::IN_PROCESS, JobOrderStateEnum::SUBMITTED]));

        foreach ($items as $item) {
            /** @var JobOrder $jobOrder */
            $jobOrder  = $item->jobOrder;
            $artefactId = $this->resolveArtefactId($production, $item);

            if ($artefactId) {
                $jobOrder->jobOrderItems()->where('artefact_id', $artefactId)->first()?->delete();
            }

            $item->update(['job_order_id' => null]);

            if (!$jobOrder->jobOrderItems()->exists()) {
                $jobOrder->delete();
            }
        }

        return $items->values()->all();
    }

    private function resolveArtefactId(Production $production, PartnerShoppingListItem $item): ?int
    {
        $orgStockId = OrgStock::where('organisation_id', $production->organisation_id)
            ->where('stock_id', $item->stock_id)
            ->value('id');

        return $orgStockId
            ? Artefact::where('production_id', $production->id)->where('org_stock_id', $orgStockId)->value('id')
            : null;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
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

    /** @return array<int, PartnerShoppingListItem> */
    public function action(Production $production, array $ids): array
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, ['ids' => $ids]);

        return $this->handle($production, $ids);
    }

    /** @return array<int, PartnerShoppingListItem> */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['ids']);
    }

    public function htmlResponse(array $items): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => trans_choice(':count item back to preparing|:count items back to preparing', count($items), ['count' => count($items)]),
        ]);
    }
}
