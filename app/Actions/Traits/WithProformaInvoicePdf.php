<?php

namespace App\Actions\Traits;

use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Sentry;
use Symfony\Component\HttpFoundation\Response;

trait WithProformaInvoicePdf
{
    public function handle(Order $order, array $options): Response
    {

        $locale = $order->shop->language->code;
        app()->setLocale($locale);

        try {
            $totalItemsNet = $order->total_amount;
            $totalShipping = $order->shipping_amount ?? 0;

            $totalNet = $totalItemsNet + $totalShipping;


            $transactionModel = $order->transactions->whereIn('model_type', ['Product', 'Service']);

            /** A delivery note with shortfalls means the order will be invoiced on what was
             * actually picked, so the proforma shows picked quantities and amounts, not the
             * submitted ones */
            $deliveryNote         = $order->deliveryNotes()->whereNot('state', DeliveryNoteStateEnum::CANCELLED)->orderByDesc('id')->first();
            $taxBreakdownOverride = null;

            if ($deliveryNote) {
                $invoiceGenerator = GenerateInvoiceFromOrder::make();

                foreach ($transactionModel as $transaction) {
                    $totals                        = $invoiceGenerator->recalculateTransactionTotals($transaction, $deliveryNote);
                    $transaction->quantity_ordered = $totals['quantity'];
                    $transaction->gross_amount     = round($totals['gross_amount'], 2);
                    $transaction->net_amount       = round($totals['net_amount'], 2);
                }

                $orderTotals          = $invoiceGenerator->recalculateTotals($order, $deliveryNote);
                $taxBreakdownOverride = $orderTotals['tax_breakdown'];

                $order->net_amount   = $orderTotals['net_amount'];
                $order->total_amount = $orderTotals['total_amount'];
                $order->tax_amount   = $orderTotals['tax_amount'];
                $order->goods_amount = $orderTotals['goods_amount'];
                $order->gross_amount = $orderTotals['gross_amount'];
            }

            $transactions = $transactionModel->map(function ($transaction) {
                if (!empty($transaction->data['date'])) {
                    $transaction->handling_date = Carbon::parse($transaction->data['date'])->format('d M Y');
                }

                return $transaction;
            });

            $amountToDeduct = (float) ($order->payment_amount ?? 0);

            $config = [
                'title'                  => $order->reference,
                'margin_left'            => 8,
                'margin_right'           => 8,
                'margin_top'             => 2,
                'margin_bottom'          => 2,
                'auto_page_break'        => true,
                'auto_page_break_margin' => 10,
            ];


            $filename = $order->slug.'-'.now()->format('Y-m-d');
            $pdf      = PDF::loadView('invoices.templates.pdf.proforma-invoice', [
                'shop'                 => $order->shop,
                'order'                => $order,
                'transactions'         => $transactions,
                'taxBreakdownOverride' => $taxBreakdownOverride,
                'totalItemsNet'        => $totalItemsNet,
                'totalShipping'        => $totalShipping,
                'totalNet'             => $totalNet,
                'amountToDeduct'       => $amountToDeduct,
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
}
