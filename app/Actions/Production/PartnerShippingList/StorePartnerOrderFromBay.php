<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Models\Procurement\PartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StorePartnerOrderFromBay extends OrgAction
{
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

    /**
     * The cut-off: whatever the partner asked for that already sits in their bay becomes an
     * order and goes straight to the warehouse. What is not in the bay yet stays on the list.
     *
     * @return array<int, Order>
     *
     * @throws \Throwable
     */
    public function handle(OrgPartner $orgPartner): array
    {
        $inTheMaking = collect(GetPartnerOrdersInTheMaking::run($orgPartner->organisation))->firstWhere('org_partner_id', $orgPartner->id);

        if (!$inTheMaking || !$inTheMaking['lines']) {
            throw ValidationException::withMessages(['order' => __('Nothing this partner asked for is in stock yet')]);
        }

        $lineStockIds       = PartnerShoppingListItem::whereIn('id', collect($inTheMaking['lines'])->pluck('id'))->pluck('stock_id', 'id');
        $mismatchedStockIds = EnsurePartnerOrderPackedInMatches::make()->mismatchedStockIds($orgPartner, $lineStockIds->unique()->values()->all());
        $lines              = collect($inTheMaking['lines'])->reject(fn ($line) => in_array($lineStockIds->get($line['id']), $mismatchedStockIds))->values()->all();

        if (!$lines) {
            throw ValidationException::withMessages(['order' => EnsurePartnerOrderPackedInMatches::make()->mismatches($orgPartner, $mismatchedStockIds)]);
        }

        return DB::transaction(function () use ($orgPartner, $lines) {
            $picked = CherryPickPartnerShoppingListItems::make()->action($orgPartner->organisation, $lines);

            if ($picked['skipped']) {
                throw ValidationException::withMessages(['order' => collect($picked['skipped'])->pluck('reason')->unique()->implode(', ')]);
            }

            foreach ($picked['orders'] as $order) {
                SendPartnerOrderToWarehouse::make()->action($order->refresh());
            }

            return $picked['orders'];
        });
    }

    /**
     * @return array<int, Order>
     *
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Production $production, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        if ($orgPartner->organisation_id !== $organisation->id) {
            abort(404);
        }

        return $this->handle($orgPartner);
    }

    /**
     * @return array<int, Order>
     *
     * @throws \Throwable
     */
    public function action(OrgPartner $orgPartner): array
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, []);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
