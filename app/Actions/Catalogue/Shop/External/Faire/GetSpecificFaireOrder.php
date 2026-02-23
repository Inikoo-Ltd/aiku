<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use integration\PHP8\ConstructorPromotionTest;

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
