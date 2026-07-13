<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

trait WithAbandonedCartRecoveryContent
{
    public function getCheckoutUrl(Outbox $outbox): string
    {
        $website = $outbox->shop->website;

        if (!$website) {
            return '';
        }

        return $website->getFullUrl().'/checkout';
    }

    public function generateRecoveryContent(int $orderId, string $checkoutUrl): string
    {
        $order = Order::find($orderId);

        if (!$order) {
            return '';
        }

        $productIds = $order->itemTransactions()
            ->pluck('model_id')
            ->all();

        $displayProducts = array_slice($productIds, 0, 5);
        $remainingCount = count($productIds) - count($displayProducts);

        $html = '';

        if ($checkoutUrl) {
            $html .= '<p style="text-align:center; margin:0 0 20px;">
                <a ses:no-track href="'.$checkoutUrl.'"
                   style="display:inline-block;
                          background:#2563eb;
                          color:#ffffff;
                          font-family: Helvetica, Arial, sans-serif;
                          font-size:15px;
                          font-weight:600;
                          text-decoration:none;
                          padding:12px 24px;
                          border-radius:6px;">'
                .__('Complete your order').
                '</a>
            </p>';
        }

        $html .= '<table width="100%" cellpadding="8" cellspacing="0"
        style="font-family: Helvetica, Arial, sans-serif;
               font-size: 14px;
               border-collapse: collapse;">';

        foreach ($displayProducts as $productId) {
            $dataProduct = Product::find($productId);

            if (!$dataProduct || !$dataProduct->webpage) {
                continue;
            }

            $productImage = Arr::get($dataProduct->imageSources(200, 200), 'png');
            $url = $dataProduct->webpage->getCanonicalUrl();
            $name = $dataProduct->name;

            $html .= '
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:12px;">';

            if ($productImage) {
                $html .= '
                <img src="'.$productImage.'"
                     width="60"
                     height="60"
                     style="display:block;
                            border-radius:6px;
                            object-fit:cover;" />';
            }

            $html .= '
                            </td>
                            <td style="vertical-align:middle;">
                                <a ses:no-track href="'.$url.'"
                                   style="color:#2563eb;
                                    text-decoration:underline;
                                    font-weight:600;">'
                .$name.
                '</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
        }

        $html .= '</table>';

        if ($remainingCount > 0) {
            $html .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #555; margin-top: 12px;">';
            $html .= __('and ').$remainingCount.($remainingCount > 1 ? __(' mores') : __(' more'));
            $html .= '</p>';
        }

        return $html;
    }
}
