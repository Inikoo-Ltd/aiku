<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CreateProspectFromWebBlock extends IrisAction
{
    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): Prospect
    {
        return StoreProspect::make()->action($this->shop, [
            'is_opt_in' => true,
            'email'     => Arr::get($modelData, 'email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:500',
                        new IUnique(
                            table: 'prospects',
                            extraConditions: [
                                ['column' => 'shop_id', 'value' => $this->shop->id],
                            ]
                        )
                ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            abort(404);
        }

        if(Customer::where('email',$this->get('email'))
            ->where('shop_id', $this->shop->id)
            ->exists()){
            $validator->errors()->add('email', __('This email is already registered as a customer in this shop.'));
        }



    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Prospect
    {

        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
