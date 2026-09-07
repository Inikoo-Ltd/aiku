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
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaEbayCustomerSalesChannel extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        return UpdateEbayCustomerSalesChannel::run($customerSalesChannel, $modelData);
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->setSanitizeFields([
            'name',
            'return_description',
        ]);
        $this->sanitizeInputs();
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
            'do_not_update_prices' => ['sometimes', 'boolean'],
            'prices_follow_rrp' => ['sometimes', 'boolean'],
            'upload_as_draft' => ['sometimes', 'boolean'],
            'tax_category_id'   => ['sometimes', 'nullable', 'integer', Rule::exists('tax_categories', 'id')],
            'status'       => ['sometimes', Rule::enum(CustomerSalesChannelStatusEnum::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'shipping_service'              => ['sometimes', 'nullable', 'string'],
            'shipping_price'              => ['sometimes', 'nullable', 'integer'],
            'shipping_max_dispatch_time'              => ['sometimes', 'nullable', 'integer'],

            'return_policy_id' => ['sometimes', 'required', 'string'],
            'payment_policy_id' => ['sometimes', 'required', 'string'],
            'fulfillment_policy_id' => ['sometimes', 'required', 'string'],

            'stock_update' => ['sometimes', 'boolean'],
            'stock_threshold' => ['sometimes', 'nullable', 'numeric'],
            'max_quantity_advertise' => ['sometimes', 'nullable', 'numeric'],

            'return_accepted' => ['sometimes', 'required', 'boolean'],
            'return_payer' => ['sometimes', 'required_if:return_accepted,true'],
            'return_within' => ['sometimes', 'required_if:return_accepted,true'],
            'return_description' => ['nullable', 'string'],

            'pricing_type' => ['sometimes', Rule::in(['percent', 'fixed', 'not_follow'])],
            'pricing_value' => ['sometimes', 'numeric', 'gte:-100'],
            'pricing_reset_all' => ['sometimes', 'boolean']
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);

        if (
            Arr::get($this->validatedData, 'do_not_update_prices')
            || Arr::get($this->validatedData, 'prices_follow_rrp') === false
            || Arr::get($this->validatedData, 'pricing_type') === 'not_follow'
        ) {
            $request->session()->flash('notification', [
                'status'      => 'success',
                'title'       => __('Prices are in your hands'),
                'description' => __('You are now free to set your own prices on eBay, we will not upload or overwrite them.'),
            ]);
        }
    }
}
