<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CustomerClaim;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Actions\CRM\CustomerClaim\StoreCustomerClaim;
use App\Actions\IrisAction;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * The customer sends their claim form (HELP-4869). Only fields are checked here; whether the
 * order, lines, quantities, outcome and photos are allowed is checked by StoreCustomerClaim.
 */
class StoreIrisCustomerClaim extends IrisAction
{
    use WithIrisClaimCustomer;

    public function rules(): array
    {
        return [
            'order_id'                => ['required', 'integer'],
            'source'                  => ['required', Rule::in([CustomerClaimSourceEnum::MERLIN->value, CustomerClaimSourceEnum::ACCOUNT->value, CustomerClaimSourceEnum::CHAT->value, CustomerClaimSourceEnum::EMAIL->value])],
            'conversation'            => ['sometimes', 'nullable', 'string', 'ulid'],
            'resolution'              => ['required', 'in:refund_to_balance,replacement,next_order'],
            'comment'                 => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines'                   => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.transaction_id'  => ['required', 'integer'],
            'lines.*.quantity'        => ['required', 'integer', 'min:1'],
            'lines.*.reason'          => ['required', 'string'],
            'lines.*.photos'          => ['sometimes', 'array', 'max:'.GetCustomerClaimForm::MAX_PHOTOS_PER_LINE],
            'lines.*.photos.*'        => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'box_photo'               => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'lines.required'            => __('Please tick the items that went wrong.'),
            'lines.*.reason.required'   => __('Please choose what happened.'),
            'lines.*.quantity.*'        => __('Please enter how many.'),
            'lines.*.photos.*.image'    => __('Photos must be images.'),
            'lines.*.photos.*.mimes'    => __('Photos must be JPG, PNG, WebP or GIF.'),
            'lines.*.photos.*.max'      => __('Each photo can be up to 10 MB.'),
            'lines.*.photos.max'        => __('Up to :count photos per item.', ['count' => GetCustomerClaimForm::MAX_PHOTOS_PER_LINE]),
            'box_photo.*'               => __('The box photo must be a JPG, PNG, WebP or GIF image up to 10 MB.'),
            'resolution.*'              => __('Please choose what you would like us to do.'),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);
        $customer = $this->claimCustomer();
        $order    = $this->claimOrder($customer, (int) $this->validatedData['order_id']);

        $conversation = null;
        if ($ulid = Arr::get($this->validatedData, 'conversation')) {
            $conversation = ShoppingAssistantConversation::where('ulid', $ulid)
                ->where('customer_id', $customer->id)
                ->where('website_id', $this->website->id)
                ->where('status', '!=', ShoppingAssistantConversationStatusEnum::CLEARED)
                ->whereNull('wiped_at')
                ->first();
        }

        $claim = StoreCustomerClaim::run($customer, $order, $this->validatedData, CustomerClaimSourceEnum::from($this->validatedData['source']), $conversation);

        return ShowIrisCustomerClaim::make()->summary($claim->refresh());
    }
}
