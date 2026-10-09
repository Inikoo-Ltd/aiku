<?php

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithCrmTagsAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();

        if (str_starts_with($routeName, 'grp.org.shops.show.crm.')) {
            $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.edit");

            if (str_ends_with($routeName, '.index')) {
                return $request->user()->authTo(
                    [
                        "crm.{$this->shop->id}.view",
                        "accounting.{$this->shop->organisation_id}.view"
                    ]
                );
            }

            return $this->canEdit;
        }

        if (str_starts_with($routeName, 'grp.models.customer.tags.')) {
            if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
                return $request->user()->authTo("fulfilment-shop.{$this->shop->fulfilment->id}.edit");
            }

            return $request->user()->authTo("crm.{$this->shop->id}.edit");
        }

        return true;
    }
}
