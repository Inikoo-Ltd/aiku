<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Actions\Procurement\OrgAgent\UI\IndexOrgAgents;
use App\Actions\Procurement\OrgPartner\UI\IndexOrgPartners;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * One small card per manufacturing hub and agent we buy from: how much is running out,
 * the sales it will cost, what is ordered and what is on the water, each linking to its full card.
 */
class GetProcurementCounterpartyCards
{
    use AsObject;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Organisation $organisation): array
    {
        $partnersAction = IndexOrgPartners::make();
        $agentsAction   = IndexOrgAgents::make();
        $orgAgents      = $agentsAction->handle($organisation)->filter(fn (OrgAgent $orgAgent) => $orgAgent->status)->values();
        $agentCover     = $agentsAction->coverByAgent($orgAgents);

        $partners = $partnersAction->handle($organisation)->filter(fn (OrgPartner $orgPartner) => $orgPartner->partner->is_manufacturing_hub)->map(function (OrgPartner $orgPartner) use ($partnersAction, $organisation) {
            $card    = $partnersAction->partnerCard($orgPartner, topLimit: 0);
            $buckets = collect($card['stats']['rescuable']['buckets'] ?? []);
            $current = collect($card['stats']['current'] ?? []);

            return $this->card(
                type: 'hub',
                name: $card['name'],
                countryCode: $card['country_code'],
                buckets: $buckets,
                orders: $current->whereIn('type', ['purchase_order', 'shopping_list']),
                containers: $current->where('type', 'stock_delivery'),
                url: route('grp.org.procurement.org_partners.index', [$organisation->slug]),
            );
        });

        $agents = $orgAgents->map(function (OrgAgent $orgAgent) use ($agentsAction, $agentCover, $organisation) {
            $card    = $agentsAction->agentCard($orgAgent);
            $current = collect($card['current']);

            return $this->card(
                type: 'agent',
                name: $card['name'],
                countryCode: $card['country_code'],
                buckets: collect($agentCover[$orgAgent->id] ?? []),
                orders: $current->where('type', 'agent_order'),
                containers: $current->where('type', 'stock_delivery'),
                url: route('grp.org.procurement.org_agents.index', [$organisation->slug]),
            );
        });

        return $partners->concat($agents)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $type, string $name, ?string $countryCode, $buckets, $orders, $containers, string $url): array
    {
        return [
            'type'         => $type,
            'name'         => $name,
            'country_code' => $countryCode,
            'out'          => (int) $buckets->firstWhere('bucket', 'out')['count'],
            'doomed'       => (int) $buckets->firstWhere('bucket', 'w1')['count'],
            'lost'         => round((float) $buckets->sum('lost')),
            'orders'       => $orders->count(),
            'orders_value' => round((float) $orders->sum('value')),
            'containers'   => $containers->count(),
            'url'          => $url,
        ];
    }
}
