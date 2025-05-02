<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 12:24:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Appearance;

use App\Actions\Helpers\Colour\GetRandomColour;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ResetModelColours
{
    use AsAction;

    public function handle(): void
    {
        foreach (Organisation::all() as $organisation) {
            $organisation->update([
                'colour' => GetRandomColour::run()
            ]);
        }

        foreach (Shop::all() as $shop) {
            $shop->update([
                'colour' => GetRandomColour::run()
            ]);
        }

        foreach (InvoiceCategory::all() as $invoiceCategory) {
            $invoiceCategory->update([
                'colour' => GetRandomColour::run()
            ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'reset:colours';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

}
