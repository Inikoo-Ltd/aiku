<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Jun 2024 23:34:44 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithWebEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();
        $user      = $request->user();


        if ($routeName == 'grp.websites.webpage.preview') {
            return true;
        }

        if (str_starts_with($routeName, 'grp.overview.')) {
            return false;
        }

        if (str_starts_with($routeName, 'grp.org.shops.show.web.')) {
            return $user->authTo([
                "supervisor-web.{$this->shop->id}",
                "web.{$this->shop->id}.edit",
                "group-webmaster.view"
            ]);
        }

        if (str_starts_with($routeName, 'grp.org.fulfilments.show.web.')) {
            return $user->authTo([
                "supervisor-fulfilment-shop.{$this->fulfilment->id}",
                "fulfilment-shop.{$this->fulfilment->id}.edit",
                "group-webmaster.view"
            ]);
        }

        if (str_starts_with($routeName, 'grp.models.')) {
            $permissions = [
                "group-webmaster.view",
                "supervisor-web.{$this->shop->id}",
                "web.{$this->shop->id}.edit",
            ];
            if (
                isset($this->shop->type)
                && $this->shop->type === ShopTypeEnum::FULFILMENT
                && isset($this->shop->fulfilment)
            ) {
                $permissions[] = "supervisor-fulfilment-shop.{$this->shop->fulfilment->id}";
                $permissions[] = "fulfilment-shop.{$this->shop->fulfilment->id}.edit";
            }

            return $user->authTo($permissions);
        }

        return false;
    }
}
