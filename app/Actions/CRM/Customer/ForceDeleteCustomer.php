<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\WebUser\DeleteWebUser;
use App\Actions\Ordering\Order\DeleteOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationArgument;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceDeleteCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithOrganisationArgument;


    public string $commandSignature = 'force_delete:customer {slug}';


    public function handle(Customer $customer): void
    {
        foreach ($customer->webUsers as $webUser) {
            DeleteWebUser::run($webUser, true);
        }
        DB::table('customer_comms')->where('customer_id', $customer->id)->delete();
        DB::table('customer_stats')->where('customer_id', $customer->id)->delete();
        DB::table('audits')->where('customer_id', $customer->id)->delete();
        DB::table('audits')->where('auditable_type', 'Customer')->where('auditable_id', $customer->id)->delete();
        DB::table('universal_searches')->where('customer_id', $customer->id)->delete();
        DB::table('universal_searches')->where('model_type', 'Customer')->where('model_id', $customer->id)->delete();


        $deliveryAddress = $customer->deliveryAddress;
        $billingAddress  = $customer->address;
        $otherAddresses  = $customer->addresses()->get();

        $customer->deliveryAddress()->dissociate();
        $customer->address()->dissociate();
        $customer->saveQuietly();

        $customer->addresses()->detach();

        $deliveryAddress?->forceDelete();
        $billingAddress?->forceDelete();
        foreach ($otherAddresses as $address) {
            $address->forceDelete();
        }
        foreach ($customer->orders as $order) {
            DeleteOrder::run($order, true);
        }

        $customer->taxNumber?->forceDelete();
        $customer->tags()->detach();

        if ($customer->fulfilmentCustomer) {
            DB::table('pallets')->where('fulfilment_customer_id', $customer->fulfilmentCustomer->id)->delete();
            $customer->fulfilmentCustomer->storedItems()->delete();
            $customer->fulfilmentCustomer->transactions()->delete();
            $customer->fulfilmentCustomer->palletDeliveries()->delete();

            $palletReturnIds = DB::table('pallet_returns')->where('fulfilment_customer_id', $customer->fulfilmentCustomer->id)->pluck('id');
            DB::table('pallet_stored_items')->whereIn('pallet_return_id', $palletReturnIds)->delete();
            DB::table('pallet_returns')->whereIn('id', $palletReturnIds)->delete();

            $customer->fulfilmentCustomer->recurringBills()->delete();

            $rentalAgreementIds = DB::table('rental_agreements')->where('fulfilment_customer_id', $customer->fulfilmentCustomer->id)->pluck('id');
            DB::table('rental_agreements')->whereIn('id', $rentalAgreementIds)->update(['current_snapshot_id' => null]);
            DB::table('rental_agreement_stats')->whereIn('rental_agreement_id', $rentalAgreementIds)->delete();
            DB::table('rental_agreement_snapshots')->whereIn('rental_agreement_id', $rentalAgreementIds)->delete();
            DB::table('rental_agreement_clauses')->whereIn('rental_agreement_id', $rentalAgreementIds)->delete();
            DB::table('recurring_bills')->whereIn('rental_agreement_id', $rentalAgreementIds)->delete();
            DB::table('rental_agreements')->whereIn('id', $rentalAgreementIds)->delete();
            $customer->fulfilmentCustomer->forceDelete();
        }

        $customer->forceDelete();
    }

    public function asCommand(Command $command): int
    {
        try {
            $customer = Customer::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('Customer not found');

            return 1;
        }

        $customerName = $customer->name;
        $this->handle($customer);

        $command->info('Customer '.$customerName.' deleted');


        return 0;
    }


}
