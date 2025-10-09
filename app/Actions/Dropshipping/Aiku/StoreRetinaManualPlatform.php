<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCustomerSalesChannels;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class StoreRetinaManualPlatform extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): CustomerSalesChannel
    {
        $platform = Platform::where('type', PlatformTypeEnum::MANUAL->value)->first();


        $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
            $customer,
            $platform,
            [
                'reference' => (string)$customer->id,
                'name'      => Arr::get($modelData, 'name'),
                'state'     => CustomerSalesChannelStateEnum::READY,
            ]
        );

        CustomerHydrateCustomerSalesChannels::dispatch($customer->id);
        CustomerSalesChannelsHydratePortfolios::dispatch($customerSalesChannel);

        return $customerSalesChannel;
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel): Response
    {
        return Inertia::location(route('retina.dropshipping.customer_sales_channels.show', [
            'customerSalesChannel' => $customerSalesChannel->slug
        ]));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                new IUnique(
                    table: 'customer_sales_channels',
                    extraConditions: [
                        [
                            'column' => 'customer_id',
                            'value'  => $this->customer->id,
                        ],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ]
        ];
    }

    public function asController(ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
