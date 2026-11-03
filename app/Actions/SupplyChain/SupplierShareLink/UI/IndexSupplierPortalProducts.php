<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 13:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentSales;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier's products on its page, a page at a time: searched by code or name and filtered by status, each with the
 * packs sold and their value at the supplier's price in the last twelve whole months and the twelve before. A product
 * with nothing sold before has no percentage, it is new.
 */
class IndexSupplierPortalProducts
{
    use AsAction;

    public const int PER_PAGE = 50;

    public const array STATUSES = ['all', 'active', 'on_hold', 'discontinued'];

    private const array DISCONTINUED_STATES = [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED];

    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int}
     */
    public function handle(Supplier $supplier, ?string $token = null, string $search = '', string $status = 'all', int $page = 1): array
    {
        $products = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw('code ilike ?', ['%'.addcslashes($search, '%_\\').'%'])
                ->orWhereRaw('name ilike ?', ['%'.addcslashes($search, '%_\\').'%'])))
            ->when($status === 'discontinued', fn (Builder $query) => $query->whereIn('state', self::DISCONTINUED_STATES))
            ->when($status === 'on_hold', fn (Builder $query) => $query->whereNotIn('state', self::DISCONTINUED_STATES)->where('is_available', false))
            ->when($status === 'active', fn (Builder $query) => $query->whereNotIn('state', self::DISCONTINUED_STATES)->where('is_available', true))
            ->with(['image', 'tradeUnits.image'])
            ->orderBy('code')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $sold        = $this->sold($products->getCollection()->pluck('id')->all());
        $stateLabels = SupplierProductStateEnum::labels();

        return [
            'data'         => $products->getCollection()->map(function (SupplierProduct $product) use ($sold, $stateLabels, $token) {
                $imageOwner   = $product->image_id ? $product : $product->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
                $pricePerPack = (float) $product->cost * ($product->units_per_pack ?: 1);
                $packs        = round((float) ($sold[$product->id]->packs ?? 0), 1);
                $previous     = round((float) ($sold[$product->id]->previous_packs ?? 0), 1);
                $status       = match (true) {
                    in_array($product->state, self::DISCONTINUED_STATES, true) => 'discontinued',
                    !$product->is_available => 'on_hold',
                    default => 'active',
                };

                return [
                    'id'             => $product->id,
                    'code'           => $product->code,
                    'name'           => $product->name,
                    'image'          => $imageOwner?->imageSources(80, 80),
                    'image_full'     => $imageOwner?->image ? $this->fullResolutionUrls($imageOwner->image, $token) : null,
                    'status'         => $status,
                    'status_label'   => ['active' => __('Active'), 'on_hold' => __('On hold'), 'discontinued' => $stateLabels[$product->state->value] ?? __('Discontinued')][$status],
                    'price'          => (float) $product->cost,
                    'units_per_pack' => $product->units_per_pack ?: 1,
                    'packs'          => $packs,
                    'sold'           => round($packs * $pricePerPack, 2),
                    'previous'       => round($previous * $pricePerPack, 2),
                    'change'         => $previous > 0 ? round(($packs / $previous - 1) * 100, 1) : null,
                ];
            })->values()->all(),
            'current_page' => $products->currentPage(),
            'last_page'    => $products->lastPage(),
            'per_page'     => $products->perPage(),
            'total'        => $products->total(),
        ];
    }

    /**
     * @param  list<int>  $supplierProductIds
     * @return Collection<int, object>
     */
    private function sold(array $supplierProductIds): Collection
    {
        if (!$supplierProductIds) {
            return collect();
        }

        $to           = now()->subMonthNoOverflow()->format('Y-m');
        $from         = now()->subMonthsNoOverflow(12)->format('Y-m');
        $previousTo   = now()->subMonthsNoOverflow(13)->format('Y-m');
        $previousFrom = now()->subMonthsNoOverflow(24)->format('Y-m');
        $packs        = '(r.cogs_org_currency / nullif(os.sku_value, 0)) * '.ShowAgentSales::EXTERNAL_SHARE.' / '.GetAgentSalesProducts::suppliersOfSql('os.id');

        return DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->join('org_stocks as os', 'os.id', 'link.org_stock_id')
            ->join('org_stock_time_series as ts', fn ($join) => $join->on('ts.org_stock_id', 'os.id')->where('ts.frequency', TimeSeriesFrequencyEnum::MONTHLY->value))
            ->join('org_stock_time_series_records as r', 'r.org_stock_time_series_id', 'ts.id')
            ->where('link.status', true)
            ->whereIn('osp.supplier_product_id', $supplierProductIds)
            ->whereBetween('r.period', [$previousFrom, $to])
            ->groupBy('osp.supplier_product_id')
            ->selectRaw("osp.supplier_product_id,
                sum(case when r.period between ? and ? then $packs end) as packs,
                sum(case when r.period between ? and ? then $packs end) as previous_packs", [$from, $to, $previousFrom, $previousTo])
            ->get()
            ->keyBy('supplier_product_id');
    }

    /**
     * The supplier opens the image through its own link; staff previewing the page through the staff media route.
     *
     * @return array{original_url: string, download_url: string}
     */
    private function fullResolutionUrls(Media $media, ?string $token): array
    {
        if ($token === null) {
            return $media->fullResolutionUrls();
        }

        return [
            'original_url' => route('grp.supplier_share.image', ['token' => $token, 'media' => $media->ulid, 'inline' => true]),
            'download_url' => route('grp.supplier_share.image', ['token' => $token, 'media' => $media->ulid]),
        ];
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'page'   => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array{search?: string|null, status?: string|null, page?: int}  $validated
     */
    public function fromRequest(Supplier $supplier, ?string $token, array $validated): JsonResponse
    {
        return response()->json($this->handle(
            $supplier,
            $token,
            trim((string) ($validated['search'] ?? '')),
            $validated['status'] ?? 'all',
            (int) ($validated['page'] ?? 1)
        ));
    }

    public function asController(string $token, ActionRequest $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->fromRequest($link->supplier, $token, $request->validated());
    }
}
