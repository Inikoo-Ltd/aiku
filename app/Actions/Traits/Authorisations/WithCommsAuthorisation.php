<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithCommsAuthorisation
{
    use WithGroupMarketingReaders;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = (string) $request->route()?->getName();

        $this->canEdit = str_starts_with($routeName, 'grp.org.fulfilments.')
            ? $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view")
            : $request->user()->authTo([
                "shop-admin.{$this->shop->id}",
                "marketing.{$this->shop->id}.view",
                "web.{$this->shop->id}.view",
                "orders.{$this->shop->id}.view",
                "crm.{$this->shop->id}.view",
            ]);

        return $this->canEdit
            || (!str_ends_with($routeName, '.workshop') && $this->readsMarketingAcrossShops($request->user()));
    }
}
