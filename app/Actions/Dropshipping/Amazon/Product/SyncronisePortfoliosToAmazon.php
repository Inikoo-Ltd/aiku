<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Amazon\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfoliosToAmazon extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(AmazonUser $amazonUser, array $attributes = [])
    {
        $portfolios = $amazonUser
            ->customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            RequestApiUploadProductAmazon::run($amazonUser, $portfolio);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer', Rule::exists('portfolios', 'id')],
        ];
    }

    public function asController(AmazonUser $amazonUser, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($amazonUser, $this->validatedData);
    }
}
