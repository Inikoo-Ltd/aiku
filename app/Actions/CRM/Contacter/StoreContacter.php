<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Prospect;

use App\Actions\Comms\Email\SendNewContacterNotification;
use App\Actions\OrgAction;
use App\Actions\Traits\WithCheckCanContactByEmail;
use App\Actions\Traits\WithCheckCanContactByPhone;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithProspectPrepareForValidation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Contacter;
use App\Models\CRM\Prospect;
use App\Rules\IUnique;
use App\Rules\Phone;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreContacter extends OrgAction
{
    use WithAttributes;
    use WithProspectPrepareForValidation;
    use WithCheckCanContactByEmail;
    use WithCheckCanContactByPhone;
    use WithModelAddressActions;


    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Contacter
    {

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        $contacter = $shop->contacters()->create($modelData);

        SendNewContacterNotification::run($contacter);

        return $contacter;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'name'              => ['nullable', 'string', 'max:255'],
            'message'           => ['nullable', 'string'],
            'email'             => [
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'contacters',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],

                    ]
                ),

            ],
            'phone'             => [
                'required_without:email',
                'nullable',
                new Phone(),
                new IUnique(
                    table: 'contacters',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],

        ];

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Contacter
    {
        // if (!$audit) {
        //     Prospect::disableAuditing();
        // }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

}
