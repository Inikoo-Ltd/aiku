<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Feb 2024 21:27:03 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\Fulfilment\Pallet\UI\IndexPallets;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerWebhookTypeEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class IndexFulfilmentCustomerFromWebhook
{
    use WithActionUpdate;

    private mixed $type;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): FulfilmentCustomer
    {
        return $fulfilmentCustomer;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerWebhookTypeEnum::class)]
        ];
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request)
    {
        return match ($this->type) {
            CustomerWebhookTypeEnum::JSON->value  => IndexPallets::run($fulfilmentCustomer),
            CustomerWebhookTypeEnum::HUMAN->value => Inertia::render(
                'Webhooks',
                [
                    'title'       => __('pallets'),
                    'pageHead'    => [
                        'title'   => __('pallets'),
                        'icon'    => ['fal', 'fa-pallet'],
                    ],
                    'data'        => PalletsResource::collection(IndexPallets::run($fulfilmentCustomer)),
                ]
            )->table(IndexPallets::make()->tableStructure($fulfilmentCustomer, 'pallets')),
            default => []
        };
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): FulfilmentCustomer
    {
        $this->setRawAttributes($request->all());

        $validatedData = $this->validateAttributes();
        $this->type    = $request->input('type');

        return $this->handle($fulfilmentCustomer, $validatedData);
    }
}
