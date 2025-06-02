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
use App\Models\CRM\Customer;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PreRegisterRetinaDropshippingCustomer
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

    public function handle(Shop $shop, array $modelData): Customer
    {
        return StorePreRegisterCustomer::make()->action($shop, $modelData);
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
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Customer
    {
        $this->shop = $shop;
        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();

        return $this->handle($shop, $this->validatedData);
    }
}
