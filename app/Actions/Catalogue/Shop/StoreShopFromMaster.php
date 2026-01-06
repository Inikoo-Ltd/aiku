<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;


use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreShopRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class StoreShopFromMaster extends OrgAction
{
    use WithStoreShopRules;
    use WithModelAddressActions;

    public MasterShop $masterShop;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-admin.{$this->organisation->id}");
    }

    /**
     * @throws Throwable
     */
    public function handle(Organisation $organisation, array $modelData): Shop
    {
        data_set($modelData, 'master_shop_id', $this->masterShop->id);
        data_set($modelData, 'type', $this->masterShop->type);

        return StoreShop::make()->action($organisation, $modelData);
    }

    public function rules(): array
    {
        return $this->getStoreShopRules();
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('type') == ShopTypeEnum::FULFILMENT->value && !$this->get('warehouses')) {
            $validator->errors()->add('warehouses', 'warehouse required');
        }
    }

    /**
     * @throws Throwable
     */
    public function asController(MasterShop $masterShop, Organisation $organisation, ActionRequest $request): Shop
    {
        $this->masterShop = $masterShop;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(Shop $shop): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.catalogue.dashboard', [$this->organisation->slug, $shop->slug]);
    }
}
