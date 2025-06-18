<?php

/*
 * author Arya Permana - Kirin
 * created on 24-01-2025-08h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Comms\Email\SendCustomerWelcomeEmail;
use App\Actions\Comms\Email\SendNewCustomerNotification;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Support\Arr;

class RegisterFulfilmentCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Fulfilment $fulfilment, array $modelData): FulfilmentCustomer
    {
        $product       = Arr::pull($modelData, 'product');
        $shipment      = Arr::pull($modelData, 'shipments_per_week');
        $sizeAndWeight = Arr::pull($modelData, 'size_and_weight');
        $password      = Arr::pull($modelData, 'password');

        data_set($modelData, 'registered_at', now());
        data_set($modelData, 'status', CustomerStatusEnum::PENDING_APPROVAL);


        $customer = StoreCustomer::make()->action($fulfilment->shop, $modelData);


        $webUser = StoreWebUser::make()->action($customer, [
            'contact_name' => Arr::get($modelData, 'contact_name'),
            'username'     => Arr::get($modelData, 'email'),
            'email'        => Arr::get($modelData, 'email'),
            'password'     => $password,
            'is_root'      => true,
        ]);

        data_set($fulfilmmentCustomerModelData, 'pallets_storage', in_array('pallets_storage', $modelData['interest']));
        data_set($fulfilmmentCustomerModelData, 'items_storage', in_array('items_storage', $modelData['interest']));
        data_set($fulfilmmentCustomerModelData, 'dropshipping', in_array('dropshipping', $modelData['interest']));
        data_set($fulfilmmentCustomerModelData, 'product', $product);
        data_set($fulfilmmentCustomerModelData, 'shipments_per_week', $shipment);
        data_set($fulfilmmentCustomerModelData, 'size_and_weight', $sizeAndWeight);

        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = UpdateFulfilmentCustomer::run($customer->fulfilmentCustomer, $fulfilmmentCustomerModelData);

        SendCustomerWelcomeEmail::run($fulfilmentCustomer->customer);

        SendNewCustomerNotification::run($fulfilmentCustomer->customer);

        ShopHydrateCrmStats::run($fulfilment->shop);

        auth('retina')->login($webUser);

        return $fulfilmentCustomer;
    }




}
