<?php

/*
 * Author: Vika Aqordi
 * Created on 06-10-2025-11h-53m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;
use Symfony\Component\HttpFoundation\Response;

class EcomPdfProformaInvoice extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;
    use WithInvoicesExport;


    public function handle(Order $order, array $options): Response
    {

        $locale = $order->shop->language->code;
        app()->setLocale($locale);

        try {
            $totalItemsNet = $order->total_amount;
            $totalShipping = $order->shipping_amount ?? 0;

            $totalNet = $totalItemsNet + $totalShipping;


            $transactionModel = $order->transactions->where('model_type', 'Product');


            $transactions = $transactionModel->map(function ($transaction) {
                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });


            $config = [
                'title'                  => $order->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];


            $filename = $order?->slug.'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.proforma-invoice', [
                'shop'                 => $order->shop,
                'order'                => $order,
                'transactions'         => $transactions,
                'pro_mode'             => Arr::get($options, 'pro_mode', false),
                'country_of_origin'    => Arr::get($options, 'country_of_origin', false),
                'rrp'                  => Arr::get($options, 'rrp', false),
                'parts'                => Arr::get($options, 'parts', false),
                'commodity_codes'      => Arr::get($options, 'commodity_codes', false),
                'weight'               => Arr::get($options, 'weight', false),
                'barcode'              => Arr::get($options, 'barcode', false),
                'hide_payment_status'  => Arr::get($options, 'hide_payment_status', false),
                'cpnp'                 => Arr::get($options, 'cpnp', false),
                'group_by_tariff_code' => Arr::get($options, 'group_by_tariff_code', false),

                'totalNet' => number_format($totalNet, 2, '.', ''),
            ], [], $config);


            return response($pdf->stream($filename.'.pdf'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
        } catch (Exception $e) {
            Sentry::captureException($e);

            return response()->json(['error' => 'Failed to generate PDF'], 404);
        }
    }

    public function rules(): array
    {
        return [
            'pro_mode'             => ['sometimes', 'boolean'],
            'country_of_origin'    => ['sometimes', 'boolean'],
            'rrp'                  => ['sometimes', 'boolean'],
            'parts'                => ['sometimes', 'boolean'],
            'commodity_codes'      => ['sometimes', 'boolean'],
            'weight'               => ['sometimes', 'boolean'],
            'barcode'              => ['sometimes', 'boolean'],
            'cpnp'                 => ['sometimes', 'boolean'],
            'hide_payment_status'  => ['sometimes', 'boolean'],
            'group_by_tariff_code' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
