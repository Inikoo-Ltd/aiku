<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot\UI;

trait WithSupplierMailshotsSubNavigation
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getSuppliersSubNavigation(): array
    {
        return [
            [
                'label'    => __('Suppliers'),
                'route'    => ['name' => 'grp.supply-chain.suppliers.index', 'parameters' => ['sort' => 'code']],
                'leftIcon' => ['icon' => ['fal', 'fa-person-dolly'], 'tooltip' => __('Suppliers')],
            ],
            [
                'label'    => __('Emails'),
                'route'    => ['name' => 'grp.supply-chain.supplier_mailshots.index', 'parameters' => []],
                'leftIcon' => ['icon' => ['fal', 'fa-mail-bulk'], 'tooltip' => __('Emails to suppliers')],
            ],
        ];
    }
}
