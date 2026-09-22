<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfIrisProductIngredientsLabel extends IrisAction
{
    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Product $product): Response
    {
        $title = $product->code.' '.__('Materials/Ingredients');

        $pdf = PDF::loadView('labels.templates.pdf.product.ingredients', [
            'name'        => $product->name,
            'weight'      => $product->marketing_weight > 0 ? $product->marketing_weight.' g' : null,
            'ingredients' => $product->marketing_ingredients,
        ], [], [
            'title'         => $title,
            'format'        => [65, 27],
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $filename = $product->code.'_unit_ingredients.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Product $product, ActionRequest $request): Response
    {
        $this->initialisation($request);

        if ($product->shop_id !== $this->shop->id || blank($product->marketing_ingredients)) {
            abort(404);
        }

        return $this->handle($product);
    }
}
