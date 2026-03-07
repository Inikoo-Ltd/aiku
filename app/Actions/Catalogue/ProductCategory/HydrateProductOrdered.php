<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProductOrdered;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\ProductCategory;

class HydrateProductOrdered
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:product_ordered {organisations?*} {--S|shop= shop slug} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = ProductCategory::class;
        $this->restriction = 'family';
        $this->evadeState = ['discontinued', 'discontinuing'];
    }

    public function handle(ProductCategory $productCategory): void
    {
        FamilyHydrateProductOrdered::run($productCategory);
    }
}
