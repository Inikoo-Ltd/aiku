<?php

namespace App\Actions\Catalogue;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GetMagentoProductsAction extends RetinaAction
{
    use AsAction;

    public function handle(string|array $filters = [], ?int $limit = null): Collection|Product|null
    {
        try {
            $query = Product::query();

            if (is_string($filters)) {
                return $query->where('code', $filters)->first();
            }

            foreach ($filters as $column => $value) {
                $query->where($column, $value);
            }

            if ($limit !== null) {
                $query->limit($limit);
            }

            return $query->get();
        } catch (\Throwable $e) {
            Log::error("Failed to get local products: " . $e->getMessage());
            throw $e;
        }
    }
}

class GetProductBySkuAction extends RetinaAction
{
    use AsAction;

    public function handle(string $sku): ?Product
    {
        try {
            return Product::where('code', $sku)->first();
        } catch (\Exception $e) {
            Log::error("Failed to get local product by SKU: " . $e->getMessage());
            throw $e;
        }
    }
}

class GetProductsByStatusAction extends RetinaAction
{
    use AsAction;

    public function handle(string $status): Collection
    {
        try {
            return Product::where('status', $status)->get();
        } catch (\Exception $e) {
            Log::error("Failed to get local products by status: " . $e->getMessage());
            throw $e;
        }
    }
}
