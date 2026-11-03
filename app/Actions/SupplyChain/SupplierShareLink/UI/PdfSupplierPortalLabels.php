<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 13:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\SupplyChain\SupplierProduct\PdfSupplierProductLabels;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierShareLink;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * The barcode labels of the products the supplier ticked on its page, one PDF. Only its own products shown on the
 * page can be printed through its link.
 */
class PdfSupplierPortalLabels
{
    use AsAction;

    /**
     * @return list<int>
     */
    public static function supplierProductIds(Supplier $supplier, string $value): array
    {
        $supplierProductIds = PdfSupplierProductLabels::supplierProductIds($value);
        abort_if(count($supplierProductIds) > PdfSupplierProductLabels::MAX_PRODUCTS, 422, __('Select at most :count products', ['count' => PdfSupplierProductLabels::MAX_PRODUCTS]));

        return SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->whereIn('id', $supplierProductIds)
            ->pluck('id')
            ->all();
    }

    public static function supplier(string $token): Supplier
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $link->supplier;
    }

    public function rules(): array
    {
        return PdfSupplierProductLabels::make()->rules();
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(string $token, ActionRequest $request): Response
    {
        $supplier  = self::supplier($token);
        $validated = $request->validated();
        $level     = $validated['level'];

        $pdf = PdfSupplierProductLabels::make()->handle(
            $supplier->group,
            self::supplierProductIds($supplier, $validated['supplier_products']),
            $level,
            $validated['for_organisation'] ?? null,
            $validated['size'] ?? null,
            (int) ($validated['copies'] ?? 1)
        );

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.PdfSupplierProductLabels::make()->filename($level).'"');
    }
}
