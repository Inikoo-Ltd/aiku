<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Catalog;

use App\Models\Dropshipping\Portfolio;

/**
 * The catalogue operations this integration needs, in whichever shape the site's Wix Stores
 * catalogue version speaks. Implementations normalise responses so callers never branch on
 * version themselves.
 */
interface WixCatalog
{
    /**
     * A newly listed product is out of stock until its inventory is set, so the opening stock
     * level is part of creating it rather than a later step.
     *
     * @return array{id?: string, message?: string}
     */
    public function createProduct(Portfolio $portfolio, ?int $quantity = null): array;

    /**
     * @return array{id?: string, message?: string}
     */
    public function updateProduct(string $productId, Portfolio $portfolio): array;

    public function deleteProduct(string $productId): array;

    /**
     * @return array{id: string, name: string|null, sku: string|null, code: string|null, price: float|null, image: string|null, images: array<int, array{src: string}>}|null
     */
    public function getProduct(string $productId): ?array;

    /**
     * @return array<int, array{id: string, name: string|null, sku: string|null, code: string|null, price: float|null, image: string|null, images: array<int, array{src: string}>}>
     */
    public function searchProducts(string $query = '', int $offset = 0, int $limit = 50): array;

    /**
     * @return array<string, string> lowercased sku => product id
     */
    public function listedSkus(): array;

    public function setInventory(string $productId, int $quantity): array;

    /**
     * @param array<int, string> $imageUrls
     */
    public function addProductMedia(string $productId, array $imageUrls): array;
}
