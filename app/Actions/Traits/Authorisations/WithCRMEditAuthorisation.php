<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Feb 2025 16:04:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithCRMEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();

        if (str_starts_with($routeName, 'grp.model.') || str_starts_with($routeName, 'grp.json.')) {
            return $request->user()->authTo(
                [
                    "crm.{$this->shop->id}.prospects.edit",
                    "crm.{$this->shop->id}.edit"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.crm.prospects')) {
            return $request->user()->authTo(
                [
                    "crm.{$this->shop->id}.prospects.edit"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.crm.')) {
            return $request->user()->authTo(
                [
                    "crm.{$this->shop->id}.edit"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.fulfilments.show.crm.')) {
            return $request->user()->authTo(
                [
                    "fulfilment-shop.{$this->shop->fulfilment->id}.edit",
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.models.')) {
            if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
                return $request->user()->authTo(
                    [
                        "fulfilment-shop.{$this->shop->fulfilment->id}.edit",
                    ]
                );
            } else {
                return $request->user()->authTo(
                    [
                        "crm.{$this->shop->id}.edit"
                    ]
                );
            }
        }


        return false;
    }
}
