<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class GetSpecificFaireOrder extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
    {
        $shop = $order->shop;

        return $shop->getFaireOrder($order->external_id);
    }

    public string $commandSignature = 'faire:order {order}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();

        $this->handle($order);

        return 0;
    }
}
