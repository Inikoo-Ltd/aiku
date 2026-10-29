<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 09:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\Response;

class PdfSupplierProductLabels extends OrgAction
{
    use WithSupplyChainAuthorisation;

    public const array LEVELS = ['unit', 'sko', 'carton'];

    public const int MAX_PRODUCTS = 300;

    public const int MAX_COPIES = 100;

    public const int MAX_LABELS = 2000;

    /**
     * @param  list<int>  $supplierProductIds
     * @return array{
     *     organisations: list<array{id: int, code: string, name: string, products: int}>,
     *     organisation_id: int|null,
     *     products: list<array{id: int, code: string, name: string|null, org_stock_id: int|null, sko: string|null, skipped: array<string, string|null>}>
     * }
     */
    public function plan(Group $group, array $supplierProductIds, ?int $organisationId = null): array
    {
        $supplierProducts = SupplierProduct::where('group_id', $group->id)
            ->whereIn('id', $supplierProductIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'units_per_carton']);

        $organisations  = $this->organisations($group, $supplierProducts->pluck('id')->all());
        $organisationId = $organisations->firstWhere('id', $organisationId)['id'] ?? $organisations->sortByDesc('products')->first()['id'] ?? null;
        $orgStocks      = $this->orgStocks($supplierProducts->pluck('id')->all(), $organisationId);

        return [
            'organisations'   => $organisations->values()->all(),
            'organisation_id' => $organisationId,
            'products'        => $supplierProducts->map(function (SupplierProduct $supplierProduct) use ($orgStocks) {
                $orgStock = $orgStocks->get($supplierProduct->id);

                return [
                    'id'           => $supplierProduct->id,
                    'code'         => $supplierProduct->code,
                    'name'         => $supplierProduct->name,
                    'org_stock_id' => $orgStock?->id,
                    'sko'          => $orgStock?->code,
                    'skipped'      => array_combine(
                        self::LEVELS,
                        array_map(fn (string $level) => $this->skipReason($supplierProduct, $orgStock, $level), self::LEVELS)
                    ),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  list<int>  $supplierProductIds
     * @return Collection<int, array{id: int, code: string, name: string, products: int}>
     */
    private function organisations(Group $group, array $supplierProductIds): Collection
    {
        $productsPerOrganisation = DB::table('org_stocks')
            ->join('stock_has_supplier_products', 'stock_has_supplier_products.stock_id', 'org_stocks.stock_id')
            ->whereIn('stock_has_supplier_products.supplier_product_id', $supplierProductIds)
            ->groupBy('org_stocks.organisation_id')
            ->selectRaw('org_stocks.organisation_id, count(distinct stock_has_supplier_products.supplier_product_id) as products')
            ->pluck('products', 'organisation_id');

        return Organisation::where('group_id', $group->id)
            ->whereIn('id', $productsPerOrganisation->keys())
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Organisation $organisation) => [
                'id'       => $organisation->id,
                'code'     => $organisation->code,
                'name'     => $organisation->name,
                'products' => (int) $productsPerOrganisation[$organisation->id],
            ]);
    }

    /**
     * @param  list<int>  $supplierProductIds
     * @return Collection<int, OrgStock>
     */
    private function orgStocks(array $supplierProductIds, ?int $organisationId): Collection
    {
        if (!$organisationId) {
            return collect();
        }

        return OrgStock::query()
            ->join('stock_has_supplier_products', 'stock_has_supplier_products.stock_id', 'org_stocks.stock_id')
            ->where('org_stocks.organisation_id', $organisationId)
            ->whereIn('stock_has_supplier_products.supplier_product_id', $supplierProductIds)
            ->with('stock:id,carton_barcode')
            ->orderByRaw('org_stocks.state <> ?', [OrgStockStateEnum::ACTIVE->value])
            ->orderBy('org_stocks.id')
            ->select('org_stocks.*', 'stock_has_supplier_products.supplier_product_id as label_supplier_product_id')
            ->get()
            ->unique('label_supplier_product_id')
            ->keyBy('label_supplier_product_id');
    }

    private function skipReason(SupplierProduct $supplierProduct, ?OrgStock $orgStock, string $level): ?string
    {
        if (!$orgStock) {
            return __('This product has no SKO in this organisation');
        }

        return match ($level) {
            'unit'   => blank($orgStock->unit_barcode) ? __('This SKO has no barcode yet') : null,
            'carton' => blank($orgStock->stock?->carton_barcode) && blank($supplierProduct->units_per_carton)
                ? __('This product has no carton barcode nor units per carton')
                : null,
            default  => null,
        };
    }

    /**
     * @param  list<int>  $supplierProductIds
     *
     * @throws \Mpdf\MpdfException
     */
    public function handle(Group $group, array $supplierProductIds, string $level, ?int $organisationId = null, ?string $size = null, int $copies = 1): string
    {
        $printable = array_values(array_filter(
            $this->plan($group, $supplierProductIds, $organisationId)['products'],
            fn (array $product) => !$product['skipped'][$level]
        ));
        abort_unless($printable, 404, __('None of these labels can be printed'));
        abort_if(count($printable) * $copies > self::MAX_LABELS, 422, __('Too many labels for one file, select fewer products or copies'));

        $orgStocks = OrgStock::whereIn('id', array_column($printable, 'org_stock_id'))->get()->keyBy('id');

        $merged = new Mpdf();
        $merged->SetTitle($this->filename($level));

        foreach ($printable as $product) {
            $label = PdfOrgStockLabel::make()->handle($orgStocks[$product['org_stock_id']], $level, array_filter([
                'supplier_product' => $product['id'],
                'size'             => $size,
            ]))->getContent();

            $merged->setSourceFile(StreamReader::createByString($label));
            $template  = $merged->importPage(1);
            $labelSize = $merged->getTemplateSize($template);

            for ($copy = 0; $copy < $copies; $copy++) {
                $merged->AddPageByArray(['sheet-size' => [$labelSize['width'], $labelSize['height']], 'orientation' => 'P']);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('', Destination::STRING_RETURN);
    }

    public function filename(string $level): string
    {
        return 'supplier-products-'.$level.'-labels.pdf';
    }

    /**
     * @return list<int>
     */
    public static function supplierProductIds(string $value): array
    {
        return array_values(array_unique(array_map('intval', array_filter(explode(',', $value), 'is_numeric'))));
    }

    public function rules(): array
    {
        return [
            'supplier_products' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'level'             => ['required', 'string', 'in:'.implode(',', self::LEVELS)],
            'organisation'      => ['sometimes', 'nullable', 'integer'],
            'size'              => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(PdfOrgStockLabel::SIZES))],
            'copies'            => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_COPIES],
        ];
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromGroup(app('group'), $request);

        $supplierProductIds = self::supplierProductIds($this->validatedData['supplier_products']);
        abort_if(count($supplierProductIds) > self::MAX_PRODUCTS, 422, __('Select at most :count products', ['count' => self::MAX_PRODUCTS]));

        $level = $this->validatedData['level'];
        $pdf   = $this->handle(
            $this->group,
            $supplierProductIds,
            $level,
            $this->validatedData['organisation'] ?? null,
            $this->validatedData['size'] ?? null,
            (int) ($this->validatedData['copies'] ?? 1)
        );

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($level).'"');
    }
}
