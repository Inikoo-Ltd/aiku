<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Remembered for the session, so every platform's create flow (OAuth round trips included) gives the new channel this language.
 */
class SetRetinaNewChannelLanguage extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $this->shop->customersChooseChannelLanguage();
    }

    public function rules(): array
    {
        return [
            'language_id' => ['required', 'integer', Rule::in($this->shop->languageIdsFor($this->customer))],
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $request->session()->put(StoreCustomerSalesChannel::NEW_CHANNEL_LANGUAGE_SESSION_KEY, $this->validatedData['language_id']);
    }
}
