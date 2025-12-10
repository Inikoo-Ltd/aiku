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
        return UpdateCustomerSalesChannel::run($customerSalesChannel, $modelData);
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
            'is_vat_adjustment' => ['sometimes', 'boolean'],
            'tax_category_id'   => ['sometimes', 'nullable', 'integer', Rule::exists('tax_categories', 'id')],
            'status'       => ['sometimes', Rule::enum(CustomerSalesChannelStatusEnum::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'shipping_service'              => ['sometimes', 'string'],
            'shipping_price'              => ['sometimes', 'integer'],
            'shipping_max_dispatch_time'              => ['sometimes', 'integer'],

            'return_policy_id' => ['sometimes', 'string'],
            'payment_policy_id' => ['sometimes', 'string'],
            'fulfillment_policy_id' => ['sometimes', 'string'],

            'stock_update' => ['sometimes', 'boolean'],
            'stock_threshold' => ['sometimes', 'numeric'],
            'max_quantity_advertise' => ['sometimes', 'numeric'],

            'return_accepted' => ['sometimes', 'boolean'],
            'return_payer' => ['sometimes', 'string'],
            'return_within' => ['sometimes', 'integer'],
            'return_description' => ['nullable', 'string']
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
