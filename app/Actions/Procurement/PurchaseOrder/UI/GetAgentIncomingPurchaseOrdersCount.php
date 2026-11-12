<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;

class GetAgentIncomingPurchaseOrdersCount extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public static function channel(int $agentOrganisationId): string
    {
        return 'grp.org.'.$agentOrganisationId.'.incoming-purchase-orders';
    }

    public static function whereIncoming(Builder $query): Builder
    {
        return $query->where('purchase_orders.state', PurchaseOrderStateEnum::SUBMITTED);
    }

    /** @return array{count: int, channel: string} */
    public function handle(Organisation $organisation): array
    {
        $agent = $this->getOrganisationAgent($organisation);

        return [
            'count'   => $agent ? self::whereIncoming(PurchaseOrder::where('purchase_orders.agent_id', $agent->id))->count() : 0,
            'channel' => self::channel($organisation->id),
        ];
    }

    /** @return array{count: int, channel: string} */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }
}
