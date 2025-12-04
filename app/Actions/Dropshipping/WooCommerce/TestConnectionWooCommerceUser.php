<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class TestConnectionWooCommerceUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:test-woo {customerSalesChannel}';

    public function handle(CustomerSalesChannel $customerSalesChannel): array|null
    {
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;

        $connection = $wooCommerceUser->checkConnection();

        if (! Arr::has($connection, 'environment')) {
            throw ValidationException::withMessages(['message' => $connection ? Arr::get(json_decode($connection[0], true), 'message') : __('Invalid')]);
        }

        return null;
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

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array|null
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}
