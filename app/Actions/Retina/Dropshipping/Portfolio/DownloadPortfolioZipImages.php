<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPortfolioZipImages extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        PortfoliosZipExport::run($customerSalesChannel, Arr::get($modelData, 'ids', []));

        $filename = 'images.zip';
        $response = response()->streamDownload(function () use ($customerSalesChannel, $modelData) {
            PortfoliosZipExport::make()->handle($customerSalesChannel, Arr::get($modelData, 'ids', []));
        }, $filename);

        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->customerSalesChannel;
        if ($customerSalesChannel->customer_id != $request->user()->customer->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (! blank($request->get('ids'))) {
            $this->set('ids', explode(',', $request->get('ids')));
        }
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
