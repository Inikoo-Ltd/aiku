<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 May 2025 01:48:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Search;

use App\Models\SupplyChain\Supplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierRecordSearch implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function getJobUniqueId(Supplier $supplier): string
    {
        return $supplier->id;
    }

    public function handle(Supplier $supplier): void
    {
        if ($supplier->trashed()) {
            $supplier->universalSearch()->delete();
            return;
        }

        $supplier->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'        => $supplier->group_id,
                'sections'        => ['supply-chain'],
                'haystack_tier_1' => trim($supplier->name.' '.$supplier->email.' '.$supplier->company_name.' '.$supplier->contact_name),
            ]
        );
    }

}
