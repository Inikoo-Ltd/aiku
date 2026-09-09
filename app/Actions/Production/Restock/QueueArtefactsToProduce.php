<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class QueueArtefactsToProduce extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
        ]);
    }

    /**
     * Put our own restock lines on the To produce board: no partner, no order, just work to do.
     *
     * @param array<int, array{artefact_id: int, quantity: float, priority?: string}> $lines
     *
     * @return array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>}
     */
    public function handle(Organisation $seller, Production $production, array $lines): array
    {
        $artefacts = Artefact::query()
            ->where('production_id', $production->id)
            ->whereIn('id', collect($lines)->pluck('artefact_id'))
            ->whereNotNull('org_stock_id')
            ->with('orgStock')
            ->get()
            ->keyBy('id');

        $queued  = 0;
        $skipped = [];

        foreach ($lines as $line) {
            $artefact = $artefacts->get($line['artefact_id']);
            if (!$artefact) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'no artefact with an org stock in this factory'];
                continue;
            }

            $quantity = round((float) $line['quantity'], 3);
            if ($quantity <= 0) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'nothing to make'];
                continue;
            }

            $alreadyOpen = PartnerShoppingListItem::where('stock_id', $artefact->orgStock->stock_id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->exists();

            if ($alreadyOpen) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'already on the To produce board'];
                continue;
            }

            PartnerShoppingListItem::create([
                'group_id'         => $seller->group_id,
                'organisation_id'  => $seller->id,
                'stock_id'         => $artefact->orgStock->stock_id,
                'org_stock_id'     => $artefact->org_stock_id,
                'quantity'         => $quantity,
                'priority'         => ShoppingListItemPriorityEnum::tryFrom($line['priority'] ?? '') ?? ShoppingListItemPriorityEnum::NORMAL,
                'state'            => ShoppingListItemStateEnum::OPEN,
                'added_by_user_id' => request()->user()?->id,
            ]);

            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /** @return array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>} */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation, $production, $request->input('lines', []));
    }

    /**
     * @param array<int, array{artefact_id: int, quantity: float, priority?: string}> $lines
     *
     * @return array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>}
     */
    public function action(Organisation $seller, Production $production, array $lines): array
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, []);

        return $this->handle($seller, $production, $lines);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
