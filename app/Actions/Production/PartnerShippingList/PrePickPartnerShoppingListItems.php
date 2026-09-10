<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Actions\Production\PartnerShippingList\UI\IndexPrePickList;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class PrePickPartnerShoppingListItems extends OrgAction
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
            "procurement.{$this->organisation->id}.edit",
        ]);
    }

    /**
     * Promise stock to the partner: the line is marked pre-picked and the warehouse is told to
     * walk it to that partner's goods out location. Nothing is sold and no order is created —
     * the stock simply stops being available to anybody else once it is staged.
     *
     * @param array<int, array{id: int, quantity?: float}> $lines
     *
     * @return array{pre_picked: int, quantity: float, skipped: array<int, array{id: int, reason: string}>}
     */
    public function handle(Organisation $seller, array $lines): array
    {
        return DB::transaction(fn () => $this->prePick($seller, $lines));
    }

    /**
     * @param array<int, array{id: int, quantity?: float}> $lines
     *
     * @return array{pre_picked: int, quantity: float, skipped: array<int, array{id: int, reason: string}>}
     */
    private function prePick(Organisation $seller, array $lines): array
    {
        $items = PartnerShoppingListItem::query()
            ->whereIn('id', collect($lines)->pluck('id'))
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('pre_picked_at')
            ->where('partner_organisation_id', $seller->id)
            ->get()
            ->keyBy('id');

        $prePicked          = 0;
        $quantity           = 0.0;
        $skipped            = [];
        $touchedOrgPartners = [];
        $promised           = [];

        foreach ($lines as $line) {
            /** @var PartnerShoppingListItem|null $item */
            $item = $items->get($line['id']);
            if (!$item) {
                $skipped[] = ['id' => $line['id'], 'reason' => 'not found, not open, already pre-picked, or not addressed to this organisation'];
                continue;
            }

            $promised[$item->stock_id] ??= $this->promisedButNotStaged($seller, $item->stock_id);

            $available = (float) OrgStock::where('organisation_id', $seller->id)
                ->where('stock_id', $item->stock_id)
                ->lockForUpdate()
                ->value('quantity_available') - $promised[$item->stock_id];

            $wanted = round(min((float) ($line['quantity'] ?? $item->quantity), (float) $item->quantity, $available), 3);
            if ($wanted <= 0) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no stock available to pre-pick'];
                continue;
            }

            $remainder = round((float) $item->quantity - $wanted, 3);
            if ($remainder > 0) {
                /* What we cannot cover yet stays on the list as its own line, still waiting. */
                PartnerShoppingListItem::create([
                    ...$item->only([
                        'group_id',
                        'organisation_id',
                        'org_partner_id',
                        'partner_organisation_id',
                        'stock_id',
                        'org_stock_id',
                        'priority',
                        'needed_by',
                        'notes',
                        'added_by_user_id',
                    ]),
                    'parent_id'  => $item->id,
                    'quantity'   => $remainder,
                    'state'      => ShoppingListItemStateEnum::OPEN,
                    'created_at' => $item->created_at,
                ]);
            }

            $item->update([
                'quantity'      => $wanted,
                'pre_picked_at' => now(),
            ]);

            $touchedOrgPartners[$item->org_partner_id] = $item->orgPartner;
            $promised[$item->stock_id] += $wanted;
            $prePicked++;
            $quantity += $wanted;
        }

        foreach ($touchedOrgPartners as $orgPartner) {
            if ($orgPartner) {
                OrgPartnerHydrateShoppingListItems::dispatch($orgPartner);
            }
        }

        return ['pre_picked' => $prePicked, 'quantity' => round($quantity, 3), 'skipped' => $skipped];
    }

    /**
     * Stock already promised to earlier pre-picks is not on the shelf for anyone else, even though
     * availability only drops once the warehouse has walked it into the bay.
     */
    private function promisedButNotStaged(Organisation $seller, int $stockId): float
    {
        return (float) PartnerShoppingListItem::query()
            ->where('partner_organisation_id', $seller->id)
            ->where('stock_id', $stockId)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->whereNotNull('pre_picked_at')
            ->sum('quantity');
    }

    public function rules(): array
    {
        return [
            'lines'            => ['required', 'array', 'min:1'],
            'lines.*.id'       => ['required', 'integer'],
            'lines.*.quantity' => ['sometimes', 'nullable', 'numeric', 'min:0.001'],
        ];
    }

    /** @return array{pre_picked: int, quantity: float, skipped: array<int, array{id: int, reason: string}>} */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation, $this->validatedData['lines']);
    }

    /** Everything the pre-pick list currently offers, honouring its filters. */
    public function everything(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        $index = IndexPrePickList::make();
        $index->initialisationFromProduction($production, $request);

        return $this->handle($organisation, $index->eligibleLines($organisation));
    }

    /**
     * @param array<int, array{id: int, quantity?: float}> $lines
     *
     * @return array{pre_picked: int, quantity: float, skipped: array<int, array{id: int, reason: string}>}
     */
    public function action(Organisation $seller, array $lines): array
    {
        $this->asAction = true;
        $this->initialisation($seller, ['lines' => $lines]);

        return $this->handle($seller, $this->validatedData['lines']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
