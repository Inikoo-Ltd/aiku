<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 01:01:23 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\SupplyChain\Supplier\UI\WithSupplierInfo;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgSupplier;
use App\Support\AgentOrganisationSelection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierShowcase
{
    use AsObject;
    use WithSupplierInfo;
    use WithProcurementSerialReferences;

    public function handle(OrgSupplier $orgSupplier): array
    {
        $supplier = $orgSupplier->supplier;

        return [
            'contactCard' => [
                'created_at' => $supplier->created_at,
                'company'    => $supplier->company_name,
                'contact'    => $supplier->contact_name,
                'website'    => $supplier->contact_website,
                'location'   => $supplier->location,
                'email'      => $supplier->email,
                'extra_emails' => $supplier->data['extra_emails'] ?? [],
                'phone'      => $supplier->phone,
                'currency'   => $supplier->currency,
                'address'    => AddressResource::make($supplier->address)->getArray(),
                'photo'      => $supplier->imageSources(320, 320),
                'photo_full' => $supplier->image?->fullResolutionUrls(),
                'supplierInfo' => [
                    ...$this->supplierInfo($supplier),
                    'next_purchase_order_reference' => $this->withOrganisationLabel($this->nextPurchaseOrderReference($orgSupplier)),
                    'next_stock_delivery_reference' => $this->withOrganisationLabel($this->nextStockDeliveryReference($orgSupplier)),
                ],
            ],
        ];
    }

    /**
     * The next numbers belong to one buying organisation; on the agent's one page per supplier they say whose.
     */
    private function withOrganisationLabel(string $reference): string
    {
        return AgentOrganisationSelection::fromRequest(parameter: 'supplier')?->label($reference) ?? $reference;
    }
}
