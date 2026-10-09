<?php

namespace App\Actions\Traits\Authorisations\Ordering;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithOrderEditAuthorisation
{
    /**
     * Staff changing an order need orders edit on its shop. The same actions also serve the
     * customer's own basket and checkout (retina and iris), which keep their own checks.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ((property_exists($this, 'asAction') && $this->asAction) || !str_starts_with((string) $request->route()?->getName(), 'grp.')) {
            return true;
        }

        $shop = $this->getShopToAuthorise($request);

        return $shop && $request->user()->authTo($this->getOrderEditPermissions($shop));
    }

    /**
     * @return array<int, string>
     */
    protected function getOrderEditPermissions(Shop $shop): array
    {
        return ["orders.$shop->id.edit"];
    }

    protected function getShopToAuthorise(ActionRequest $request): ?Shop
    {
        if (isset($this->shop)) {
            return $this->shop;
        }

        foreach (['shop', 'order', 'transaction', 'customer', 'customerClient', 'deliveryNote'] as $parameter) {
            $model = $request->route($parameter);
            if ($model instanceof Shop) {
                return $model;
            }
            if ($model instanceof Model && $model->shop instanceof Shop) {
                return $model->shop;
            }
        }

        return null;
    }
}
