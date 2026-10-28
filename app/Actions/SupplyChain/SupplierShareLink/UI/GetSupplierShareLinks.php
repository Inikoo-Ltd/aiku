<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierShareLinks
{
    use AsObject;

    /**
     * What staff need to allow a supplier email to log in by itself or to give a direct link.
     *
     * @return array<string, mixed>
     */
    public function handle(Supplier $supplier, bool $canCreate): array
    {
        return [
            'can_create'  => $canCreate,
            'email'       => $supplier->email,
            'months'      => StoreSupplierShareLink::MONTHS,
            'store_route' => ['name' => 'grp.models.supplier.share_links.store', 'parameters' => ['supplier' => $supplier->id]],
            'portal_url'  => route('grp.supplier_portal.login'),
            'preview_url' => route('grp.supply-chain.suppliers.portal_preview', $supplier),
            'email_route' => ['name' => 'grp.models.supplier.portal_emails.store', 'parameters' => ['supplier' => $supplier->id]],
        ];
    }
}
