<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Traits\WithExportData;
use App\Exports\Marketing\ProductsInProductCategoryExport;
use App\Exports\Marketing\ProductsInShopExport;
use App\Exports\SupplyChain\AgentsExport;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportProductsInShop
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new ProductsInShopExport($shop), 'products', $type);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($shop, $request->all());
    }
}
