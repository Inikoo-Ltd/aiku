<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\ProductCategory\EnsureProductCategoryTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class RepairProductCategoryTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'product-category:repair-time-series {organisations?*} {--S|shop= : Shop slug}';

    public function __construct()
    {
        $this->model = ProductCategory::class;
    }

    public function handle(ProductCategory $productCategory): void
    {
        EnsureProductCategoryTimeSeries::run($productCategory);
    }
}
