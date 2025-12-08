<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 15:16:23 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydratePurchaseOrders;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateOrgSupplier
{
    use asAction;

    public string $commandSignature = 'hydrate:org_suppliers';

    public function handle(OrgSupplier $orgSupplier): void
    {
        OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
        OrgSupplierHydratePurchaseOrders::run($orgSupplier);

    }

    public function asCommand(Command $command): int
    {

        $count = OrgSupplier::count();
        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();
        OrgSupplier::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });
        $bar->finish();

        return 0;
    }
}
