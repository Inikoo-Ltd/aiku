<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;

class UploadPortfolioZipImages extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->customerSalesChannel;
        if ($customerSalesChannel->customer_id != $request->user()->customer->id) {
            return false;
        }

        return true;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): string
    {
        $this->initialisation($request);
        $key = now()->timestamp.'-'.Str::random(8);
        ProcessPortfolioZipImages::dispatch($customerSalesChannel, $key);

        return $key;
    }
}
