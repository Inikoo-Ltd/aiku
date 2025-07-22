<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Traits\WithExportData;
use App\Exports\Marketing\ProductsInProductCategoryExport;
use App\Exports\SupplyChain\AgentsExport;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportProductsInProductCategory
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory, string $type, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new ProductsInProductCategoryExport($productCategory, $type), 'products', $type);
    }

    /**
     * @throws \Throwable
     */
    public function inDepartment(ProductCategory $productCategory, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($productCategory, 'department', $request->all());
    }

    /**
     * @throws \Throwable
     */
    public function inFamily(ProductCategory $productCategory, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($productCategory, 'family', $request->all());
    }

    /**
     * @throws \Throwable
     */
    public function inSubDepartment(ProductCategory $productCategory, ActionRequest $request): BinaryFileResponse
    {
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($productCategory, 'sub_department', $request->all());
    }
}
