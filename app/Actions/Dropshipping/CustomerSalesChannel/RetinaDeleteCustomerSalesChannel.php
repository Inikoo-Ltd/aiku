<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 15:18:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class RetinaDeleteCustomerSalesChannel extends RetinaAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): ?bool
    {
        if ($customerSalesChannel->isHandedOverToExternalShop()) {
            throw ValidationException::withMessages([
                'message' => __('This channel still shares its store connection, it can not be deleted.')
            ]);
        }

        return CloseCustomerSalesChannel::run($customerSalesChannel);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $request->user()->customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Closing takes the products off the channel and stops its orders, so the customer types the channel name
     * shown in the dialog; a stray click or request can not close it.
     */
    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($validator->errors()->has('confirmation')) {
            return;
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $request->route('customerSalesChannel');
        $channelName          = $customerSalesChannel->name ?: $customerSalesChannel->reference;

        if (Str::lower(trim((string) $request->input('confirmation'))) !== Str::lower(trim((string) $channelName))) {
            $validator->errors()->add('confirmation', __('Type the channel name exactly as shown to confirm.'));
        }
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.dropshipping.customer_sales_channels.index');
    }


}
