<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:12:03 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;

class StoreOrgSupplierFromFreeSupplier extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, array $modelData = []): void
    {
        if ($supplier->agent_id) {
            return;
        }

        foreach ($this->getOrganisations($supplier) as $organisation) {
            StoreOrgSupplier::make()->action(
                $organisation,
                $supplier,
                $modelData,
                hydratorsDelay: $this->hydratorsDelay,
                strict: $this->strict
            );
        }
    }

    protected function getOrganisations(Supplier $supplier): Collection
    {
        $countryId = $supplier->address?->country_id;

        return Organisation::where('group_id', $supplier->group_id)
            ->where(function ($query) use ($countryId) {
                $query->where('type', OrganisationTypeEnum::SHOP);

                if ($countryId) {
                    $query->orWhere(
                        fn ($query) => $query->where('type', OrganisationTypeEnum::AGENT)
                            ->where('country_id', $countryId)
                    );
                }
            })
            ->get();
    }

    /**
     * @throws \Throwable
     */
    public function action(Supplier $supplier, array $modelData = [], int $hydratorsDelay = 0, bool $strict = true): void
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->handle($supplier, $modelData);
    }
}
