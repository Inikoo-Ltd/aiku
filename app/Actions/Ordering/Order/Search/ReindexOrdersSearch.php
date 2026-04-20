<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 23:04:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Search;

use App\Actions\HydrateModel;
use App\Models\Ordering\Order;
use Illuminate\Support\Collection;

class ReindexOrdersSearch extends HydrateModel
{
    public string $commandSignature = 'search:orders {organisations?*} {--s|slugs=}';


    public function handle(Order $order): void
    {

    }

    protected function getModel(string $slug): Order
    {
        return Order::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Order::withTrashed()->get();
    }
}
