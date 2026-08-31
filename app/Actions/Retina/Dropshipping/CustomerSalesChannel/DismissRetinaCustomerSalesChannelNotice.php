<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DismissRetinaCustomerSalesChannelNotice extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): CustomerSalesChannel
    {
        $settings = $customerSalesChannel->settings;
        $settings['dismissed_notices'] = array_values(array_unique([
            ...Arr::get($settings, 'dismissed_notices', []),
            Arr::get($modelData, 'notice')
        ]));
        $customerSalesChannel->update(['settings' => $settings]);

        return $customerSalesChannel;
    }

    public function rules(): array
    {
        return [
            'notice' => ['required', Rule::in(['ebay_seller'])]
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
