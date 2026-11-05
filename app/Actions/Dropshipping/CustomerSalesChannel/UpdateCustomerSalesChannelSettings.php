<?php

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCustomerSalesChannelEditAuthorisation;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Traits\SanitizeInputs;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomerSalesChannelSettings extends OrgAction
{
    use WithCustomerSalesChannelEditAuthorisation;
    use SanitizeInputs;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        if ($customerSalesChannel->user instanceof EbayUser) {
            return UpdateEbayCustomerSalesChannel::run($customerSalesChannel, $modelData);
        }

        return UpdateCustomerSalesChannel::run($customerSalesChannel, $modelData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->setSanitizeFields([
            'name',
            'return_description',
        ]);
        $this->sanitizeInputs();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name'                   => ['sometimes', 'string', 'max:255'],
            'stock_update'           => ['sometimes', 'boolean'],
            'stock_threshold'        => ['sometimes', 'nullable', 'numeric'],
            'max_quantity_advertise' => ['sometimes', 'nullable', 'numeric'],
            'is_vat_adjustment'      => ['sometimes', 'boolean'],
            'tax_category_id'        => ['sometimes', 'nullable', 'integer', Rule::exists('tax_categories', 'id')],
            'pricing_type'           => ['sometimes', Rule::in(['percent', 'fixed'])],
            'pricing_value'          => ['sometimes', 'numeric', 'gt:-100'],
            'private_tracking_notes' => ['sometimes', 'boolean'],
        ];

        if (!$this->customerSalesChannel->user instanceof EbayUser) {
            return $rules;
        }

        return [
            ...$rules,
            'pricing_type'               => ['sometimes', Rule::in(['percent', 'fixed', 'not_follow'])],
            'pricing_value'              => ['sometimes', 'numeric', 'gte:-100'],
            'pricing_reset_all'          => ['sometimes', 'boolean'],
            'upload_as_draft'            => ['sometimes', 'boolean'],
            'shipping_service'           => ['sometimes', 'nullable', 'string'],
            'shipping_price'             => ['sometimes', 'nullable', 'integer'],
            'shipping_max_dispatch_time' => ['sometimes', 'nullable', 'integer'],
            'return_accepted'            => ['sometimes', 'required', 'boolean'],
            'return_payer'               => ['sometimes', 'required_if:return_accepted,true'],
            'return_within'              => ['sometimes', 'required_if:return_accepted,true'],
            'return_description'         => ['nullable', 'string'],
            'fulfillment_policy_id'      => ['sometimes', 'required', 'string'],
            'return_policy_id'           => ['sometimes', 'required', 'string'],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
