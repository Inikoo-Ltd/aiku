<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Feb 2026 15:54:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateInvoiceIntervals;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSales;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class HydrateSubDepartmentsSales
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:sub_departments_sales {organisations?*} {--S|shop= shop slug}  {--s|slugs=} ';

    public function __construct()
    {
        $this->model       = ProductCategory::class;
        $this->restriction = 'sub_department';
    }

    public function handle(ProductCategory $productCategory): void
    {
        ProductCategoryHydrateSales::run($productCategory);
        ProductCategoryHydrateInvoiceIntervals::run($productCategory->id);
        ProductCategoryHydrateSalesIntervals::run($productCategory->id);
    }

}
