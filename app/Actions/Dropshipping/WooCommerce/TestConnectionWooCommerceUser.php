<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class TestConnectionWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:test-woo {customerSalesChannel}';

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;

        $connection = $wooCommerceUser->checkConnection();

        if (! Arr::has($connection, 'environment')) {
            $errorData = $connection;

            $wooCommerceUser->update([
                'data' => $errorData,
            ]);
        }

        CheckWooChannel::run($wooCommerceUser);
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request): void
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);
    }
}
