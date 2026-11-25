<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 01:01:23 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\SupplyChain\Supplier\UI\GetSupplierShowcase;
use App\Models\Procurement\OrgSupplier;
use App\Support\AgentOrganisationSelection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierShowcase
{
    use AsObject;
    use WithProcurementSerialReferences;

    /**
     * @param  array{name: string, parameters: array<string, mixed>}|null  $editRoute
     */
    public function handle(OrgSupplier $orgSupplier, ?array $editRoute = null): array
    {
        $showcase = GetSupplierShowcase::run($orgSupplier->supplier, $editRoute);

        $showcase['contactCard']['supplierInfo'] = [
            ...$showcase['contactCard']['supplierInfo'],
            'next_purchase_order_reference' => $this->withOrganisationLabel($this->nextPurchaseOrderReference($orgSupplier)),
            'next_stock_delivery_reference' => $this->withOrganisationLabel($this->nextStockDeliveryReference($orgSupplier)),
        ];

        return $showcase;
    }

    /**
     * The next numbers belong to one buying organisation; on the agent's one page per supplier they say whose.
     */
    private function withOrganisationLabel(string $reference): string
    {
        return AgentOrganisationSelection::fromRequest(parameter: 'supplier')?->label($reference) ?? $reference;
    }
}
