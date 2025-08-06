<?php

namespace App\Actions\Catalogue;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GetLocalProductsAction extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * Handle the action to get products from the local database.
     *
     * @param array $filters Optional array of filters (e.g., ['status' => 'active', 'is_main' => true])
     * @param int|null $limit Optional limit for the number of results
     * @return Collection<int, Product>
     * @throws \Exception
     */
    public function handle(array $filters = [], ?int $limit = null): Collection
    {
        try {
            $query = Product::query();

            foreach ($filters as $column => $value) {
                $query->where($column, $value);
            }

            if ($limit !== null) {
                $query->limit($limit);
            }

            $products = $query->get();

            return $products;
        } catch (\Exception $e) {
            Log::error("Failed to get local products: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Example of how to get a single product by its SKU.
     *
     * @param string $sku
     * @return Product|null
     * @throws \Exception
     */
    public function getProductBySku(string $sku): ?Product
    {
        try {
            return Product::where('code', $sku)->first();
        } catch (\Exception $e) {
            Log::error("Failed to get local product by SKU: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Example of how to get products by status.
     *
     * @param string $status
     * @return Collection<int, Product>
     * @throws \Exception
     */
    public function getProductsByStatus(string $status): Collection
    {
        try {
            return Product::where('status', $status)->get();
        } catch (\Exception $e) {
            Log::error("Failed to get local products by status: " . $e->getMessage());
            throw $e;
        }
    }
}
