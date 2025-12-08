<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Nov 2023 10:49:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

trait WithShopsArgument
{
    public function getShops(Command $command): \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\LazyCollection
    {

        if (! $command->argument('shops')) {
            $shops = Shop::all();
        } else {
            $shops = Shop::query()
                ->when($command->argument('shops'), function ($query) use ($command) {
                    $query->whereIn('slug', $command->argument('shops'));
                })
                ->cursor();
        }

        return $shops;
    }
}
