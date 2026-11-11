<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-11 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Product;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The live websites selling one of the supplier's products: through the SKOs the supplier product is linked to, each
 * website's product made from them, with the link to its product page and to its family page when they are live.
 */
class GetSupplierPortalProductWebsites
{
    use AsAction;

    /**
     * @return array{product: array{code: string, name: string|null}, websites: list<array{website: string, domain: string, product_code: string, product_name: string|null, product_url: string|null, family_code: string|null, family_name: string|null, family_url: string|null}>}
     */
    public function handle(Supplier $supplier, int $supplierProductId): array
    {
        /** @var SupplierProduct $supplierProduct */
        $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->findOrFail($supplierProductId);

        $orgStockIds = DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->where('link.status', true)
            ->where('osp.supplier_product_id', $supplierProduct->id)
            ->select('link.org_stock_id');

        $products = Product::query()
            ->whereIn('id', DB::table('product_has_org_stocks')->whereIn('org_stock_id', $orgStockIds)->select('product_id'))
            ->whereIn('state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING])
            ->whereHas('shop.website', fn (Builder $query) => $query->where('state', WebsiteStateEnum::LIVE))
            ->with(['shop.website', 'webpage.website', 'family.webpage.website'])
            ->get();

        $websites = $products
            ->map(fn (Product $product) => [
                'website'      => $product->shop->website->name,
                'domain'       => $product->shop->website->domain,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'product_url'  => $this->liveUrl($product->webpage),
                'family_code'  => $product->family?->code,
                'family_name'  => $product->family?->name,
                'family_url'   => $this->liveUrl($product->family?->webpage),
            ])
            ->filter(fn (array $row) => $row['product_url'] !== null || $row['family_url'] !== null)
            ->sortBy([['website', 'asc'], ['product_code', 'asc']])
            ->values()
            ->all();

        return [
            'product'  => ['code' => $supplierProduct->code, 'name' => $supplierProduct->name],
            'websites' => $websites,
        ];
    }

    private function liveUrl(?Webpage $webpage): ?string
    {
        if ($webpage === null || $webpage->state !== WebpageStateEnum::LIVE) {
            return null;
        }

        return $webpage->canonical_url ?: 'https://'.$webpage->website->domain.'/'.ltrim($webpage->url, '/');
    }

    public function asController(string $token, int $supplierProduct): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return response()->json($this->handle($link->supplier, $supplierProduct));
    }
}
