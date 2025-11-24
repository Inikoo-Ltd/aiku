<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateEbayCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaEbayCustomerSalesChannel extends RetinaAction
{
    use WithActionUpdate;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        return UpdateEbayCustomerSalesChannel::run($customerSalesChannel, $modelData);
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
            'is_vat_adjustment' => ['sometimes', 'required', 'boolean'],
            'tax_category_id'   => ['sometimes', 'nullable', 'integer', Rule::exists('tax_categories', 'id')],
            'status'       => ['sometimes', Rule::enum(CustomerSalesChannelStatusEnum::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'shipping_service'              => ['sometimes', 'required', 'string'],
            'shipping_price'              => ['sometimes', 'required', 'integer'],
            'shipping_max_dispatch_time'              => ['sometimes', 'required', 'integer'],

            'return_policy_id' => ['sometimes', 'required', 'string'],
            'payment_policy_id' => ['sometimes', 'required', 'string'],
            'fulfillment_policy_id' => ['sometimes', 'required', 'string'],

            'return_accepted' => ['sometimes', 'required', 'boolean'],
            'return_payer' => ['sometimes', 'nullable', 'string'],
            'return_within' => ['sometimes', 'nullable', 'integer'],
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
