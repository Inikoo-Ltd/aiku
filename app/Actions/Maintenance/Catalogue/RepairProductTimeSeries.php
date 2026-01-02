<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\TimeSeries\EnsureTimeSeries;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Product;

class RepairProductTimeSeries
{
    use WithHydrateCommand;

    public string $commandSignature = 'repair:product-time-series {organisations?*} {--S|shop= : Shop slug}';

    public function __construct()
    {
        $this->model = Product::class;
    }

    public function handle(Product $product): void
    {
        if ($product->asset) {
            EnsureTimeSeries::run($product->asset);
        }
    }
}
