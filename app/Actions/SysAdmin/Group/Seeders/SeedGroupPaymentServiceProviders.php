<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Accounting\PaymentServiceProvider\StorePaymentServiceProvider;
use App\Actions\Accounting\PaymentServiceProvider\UpdatePaymentServiceProvider;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedGroupPaymentServiceProviders
{
    use AsAction;

    public function handle(Group $group): void
    {
        $paymentServiceProvidersData = collect(PaymentServiceProviderEnum::values());

        $paymentServiceProvidersData->each(function ($modelData) use ($group) {
            $paymentServiceProvider = PaymentServiceProvider::where('code', $modelData)->first();

            $data = [
                'code' => $modelData,
                'type' => PaymentServiceProviderEnum::types()[$modelData],
                'name' => PaymentServiceProviderEnum::labels()[$modelData],
            ];

            if ($paymentServiceProvider) {
                UpdatePaymentServiceProvider::make()->action(
                    $paymentServiceProvider,
                    $data
                );
            } else {
                StorePaymentServiceProvider::make()->action(
                    $group,
                    $data
                );
            }

        });
    }

    public string $commandSignature = 'groups:seed-payment-service-providers';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding payment service providers for group: $group->name");
            setPermissionsTeamId($group->id);
            $this->handle($group);
        }

        return 0;
    }
}
