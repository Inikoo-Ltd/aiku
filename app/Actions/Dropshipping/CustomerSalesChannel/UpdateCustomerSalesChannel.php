<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        return $this->update($customerSalesChannel, $modelData);
    }

    public function rules(): array
    {
        return [
            'reference'          => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'customer_sales_channels',
                    extraConditions: [
                        ['column' => 'customer_id', 'value' => $this->customerSalesChannel->customer_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->customerSalesChannel->id
                        ]
                    ]
                ),
            ],
            'status'       => ['sometimes', Rule::enum(CustomerSalesChannelStatusEnum::class)],
        ];
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->asAction             = true;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->hydratorsDelay       = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->customerSalesChannel = $customerSalesChannel;

        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }


}
