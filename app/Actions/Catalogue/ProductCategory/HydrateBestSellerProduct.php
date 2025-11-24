<?php

/*
 * author Louis Perez
 * created on 21-11-2025-16h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateBestSellerProduct;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

class HydrateBestSellerProduct
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:best_seller {organisations?*} {--S|shop= shop slug} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = ProductCategory::class;
        $this->restriction = 'family';
        $this->evadeState = ['discontinued', 'discontinuing'];
    }

    public function handle(ProductCategory $productCategory): void
    {
        FamilyHydrateBestSellerProduct::run($productCategory);
    }

}
