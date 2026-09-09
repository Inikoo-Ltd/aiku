<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList\UI;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\PartnerShoppingListItem;
use Lorisleiva\Actions\ActionRequest;

class GetProductionQueueCounts extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
            "productions_procurement.{$this->production->id}.view",
        ]);
    }

    /** @return array{to_produce: int, pre_pick: int, channel: string} */
    public function handle(Organisation $seller, Production $production): array
    {
        $openItems = PartnerShoppingListItem::query()
            ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->leftJoin('org_stocks', function ($join) use ($seller) {
                $join->on('org_stocks.stock_id', 'stocks.id')
                    ->where('org_stocks.organisation_id', $seller->id);
            })
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            ->where(function ($query) use ($seller) {
                $query->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
                    ->orWhere(function ($query) use ($seller) {
                        $query->whereNull('partner_shopping_list_items.partner_organisation_id')
                            ->where('partner_shopping_list_items.organisation_id', $seller->id);
                    });
            });

        return [
            'channel'    => 'grp.org.'.$seller->id.'.production-queues',
            'to_produce' => (clone $openItems)
                ->whereExists(function ($query) use ($production) {
                    $query->from('artefacts')
                        ->whereColumn('artefacts.org_stock_id', 'org_stocks.id')
                        ->where('artefacts.production_id', $production->id)
                        ->whereNull('artefacts.deleted_at');
                })
                ->count(),
            'pre_pick' => (clone $openItems)
                ->whereNotNull('partner_shopping_list_items.partner_organisation_id')
                ->where('org_stocks.quantity_available', '>', 0)
                ->count(),
        ];
    }

    /** @return array{to_produce: int, pre_pick: int, channel: string} */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation, $production);
    }
}
