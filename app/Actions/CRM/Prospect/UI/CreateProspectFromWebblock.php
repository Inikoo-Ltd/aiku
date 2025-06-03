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
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CreateProspectFromWebblock extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Prospect
    {
        return StoreProspect::make()->action($shop, [
            'is_opt_in' => true,
            'email' => Arr::get($modelData, 'email'),
        ]);

    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:500'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Prospect
    {
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            abort(404);
        }
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
