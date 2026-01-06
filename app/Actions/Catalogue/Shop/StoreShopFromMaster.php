<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\CloneCatalogueStructure;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreShopRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
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
        return DB::transaction(function () use ($organisation, $modelData) {

            data_set($modelData, 'is_aiku', true);
            data_set($modelData, 'state', ShopStateEnum::OPEN);

            $domain = Arr::pull($modelData, 'domain');
            $shop = StoreShop::make()->action($organisation, $modelData);

            StoreWebsite::make()->action($shop, [
                'code' => $shop->code,
                'name' => $shop->name,
                'domain' => $domain,
                'status' => true,
                'state' => WebsiteStateEnum::LIVE
            ]);

            CloneCatalogueStructure::dispatch($this->masterShop, $shop);

            return $shop;
        });
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('master_shop_id', $this->masterShop->id);
        $this->set('type', $this->masterShop->type);
        $this->set('timezone_id', $this->organisation->timezone_id);
    }

    public function rules(): array
    {
        $rules = $this->getStoreShopRules();

        $rules['domain'] = ['required', 'string'];

        return $rules;

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
        return Redirect::route('grp.org.shops.show.catalogue.dashboard', [
            $this->organisation->slug,
            $shop->slug
        ]);
    }
}
