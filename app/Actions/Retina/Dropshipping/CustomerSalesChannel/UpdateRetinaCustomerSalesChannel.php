<?php
/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerSalesChannel extends RetinaAction
{
    use WithActionUpdate;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        $customerSalesChannel = UpdateCustomerSalesChannel::run($customerSalesChannel, $modelData);

        return $customerSalesChannel;
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
            'name' => ['sometimes', 'string', 'max:255']
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
