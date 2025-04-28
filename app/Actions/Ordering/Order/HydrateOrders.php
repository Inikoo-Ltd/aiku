<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Order\Hydrators\OrderHydrateOfferCampaigns;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateOfferComponents;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateOffers;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Ordering\Order;

class HydrateOrders
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:orders {organisations?*} {--s|slugs=}';


    public function handle(Order $order): void
    {
        OrderHydrateTransactions::run($order);
        OrderHydrateOfferCampaigns::run($order);
        OrderHydrateOffers::run($order);
        OrderHydrateOfferComponents::run($order);


    }

    public function __construct()
    {
        $this->model = Order::class;
    }
}
