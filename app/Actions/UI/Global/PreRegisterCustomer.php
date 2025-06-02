<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\UI\Global;

use App\Actions\CRM\Customer\StorePreRegisterCustomer;
use App\Models\Catalogue\Shop;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PreRegisterCustomer
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

    public function handle(Shop $shop, array $modelData)
    {
        if (Arr::get($modelData, 'preview')) {
            return;
        }
        StorePreRegisterCustomer::make()->action($shop, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'                    => [
                'required',
                'string',
                'max:255',
                'exclude_unless:deleted_at,null',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'preview'                => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->shop = $shop;
        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();

        $this->handle($shop, $this->validatedData);
    }
}
