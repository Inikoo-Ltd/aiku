<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDuplicatedCustomers
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Shop $shop): void
    {


        Customer::query()
            ->where('customers.shop_id', $shop->id)
            ->orderBy('id','desc')
            ->chunkById(1000, function ($customers) {
                foreach ($customers as $customer) {

                    $numberCustomersSameEmail = Customer::where('email', $customer->email)->where('shop_id',$customer->shop_id)->count();
                    if($numberCustomersSameEmail > 1 && $customer->email){


                        print "Email: $customer->email\n";
                        Customer::where('email', $customer->email)->where('shop_id',$customer->shop_id)->get()->each(function ($customer) {
                            $countWebUsers = DB::table('web_users')->where('customer_id', $customer->id)->count();
                            $orders=DB::table('orders')->where('customer_id', $customer->id)->count();
                            $portfolios=DB::table('portfolios')->where('customer_id', $customer->id)->count();
                            $csc=DB::table('customer_sales_channels')->where('customer_id', $customer->id)->count();
                            $clients=DB::table('customer_clients')->where('customer_id', $customer->id)->count();
                           print  ">> ".$customer->id."  $customer->slug  WU:$countWebUsers  O:$orders P: $portfolios CSC: $csc   CL: $clients ; $customer->source_id | $customer->post_source_id   \n";

                        });


                    }


                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_duplicated_customers {shop_id}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::find($command->argument('shop_id'));

        try {
            $this->handle($shop);

        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }


        return 0;
    }

}
