<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class SendNewOrderEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendSubscribersOutboxEmail;

    public function handle(int $orderID): void
    {
        $order = Order::find($orderID);
        if (!$order) {
            return;
        }

        /** @var Outbox $outbox */
        $outbox = $order->shop->outboxes()->where('code', OutboxCodeEnum::NEW_ORDER->value)->first();

        $customer = $order->customer;

        $transactions = $order->transactions()->where('model_type', 'Product')->get();

        $balance = 'Customer Balance: ' . $order->shop->currency->symbol . $order->customer->balance;


        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'shop_name'                    => $order->shop->name,
                'currency'                     => $order->shop->currency->symbol,
                'customer_name'                => $customer->name,
                'order_reference'              => $order->reference,
                'order_total'                  => $order->total_amount,
                'blade_order_total'            => $this->generateBladeValue($order->total_amount, $order->shop, $order->org_exchange, true),
                'goods_amount'                 => $order->goods_amount,
                'blade_goods_amount'           => $this->generateBladeValue($order->goods_amount, $order->shop, $order->org_exchange),
                'charges_amount'               => $order->charges_amount,
                'blade_charges_amount'         => $this->generateBladeValue($order->charges_amount, $order->shop, $order->org_exchange),
                'shipping_amount'              => $order->shipping_amount,
                'blade_shipping_amount'        => $this->generateBladeValue($order->shipping_amount, $order->shop, $order->org_exchange),
                'net_amount'                   => $order->net_amount,
                'blade_net_amount'             => $this->generateBladeValue($order->net_amount, $order->shop, $order->org_exchange),
                'tax_amount'                   => $order->tax_amount,
                'blade_tax_amount'             => $this->generateBladeValue($order->tax_amount, $order->shop, $order->org_exchange),
                'payment_amount'               => $order->payment_amount,
                'blade_payment_amount'         => $this->generateBladeValue($order->payment_amount, $order->shop, $order->org_exchange),
                'payment_type'                 => $order->payments()->first()->paymentAccount->name ?? 'N/A',
                'blade_new_order_transactions' => $this->generateOrderTransactionsHtml($transactions, $order->shop->currency, $order->shop->organisation->currency),
                'date'                         => $order->submitted_at->format('F jS, Y'),
                'order_link'                   => route('grp.org.shops.show.crm.customers.show.orders.show', [
                    $order->organisation->slug,
                    $order->shop->slug,
                    $order->customer->slug,
                    $order->slug
                ]),
                'customer_link'                => $customer->shop->fulfilment
                    ? route('grp.org.fulfilments.show.crm.customers.show', [
                        $customer->organisation->slug,
                        $customer->shop->fulfilment->slug,
                        $customer->fulfilmentCustomer->slug
                    ])
                    : route('grp.org.shops.show.crm.customers.show', [
                        $customer->organisation->slug,
                        $customer->shop->slug,
                        $customer->slug
                    ]),
                'platform'                     => $order->customerSalesChannel?->platform?->name ?? '',
                'balance'                      => $balance,
            ]
        );
    }

    private function generateBladeValue(?float $value, Shop $shop, ?float $orgExchangeRate, bool $isBold = false): string
    {
        $currency = $shop->currency;
        $orgCurrency = $shop->organisation->currency;
        $currenciesDiffer = $currency->id !== $orgCurrency->id;
        $exchangeRate = $orgExchangeRate ?? 1;
        $fontWeight = $isBold ? 'bold' : 'normal';
        $fontSize = $isBold ? '16px' : '14px';

        if ($currenciesDiffer) {
            return sprintf(
                '<div style="text-align: right;">
                    <div style="font-size: %s; font-weight: %s;">%s%s</div>
                    <div style="font-size: 11px; color: #666;">%s%s</div>
                </div>',
                $fontSize,
                $fontWeight,
                $orgCurrency->symbol,
                number_format($value * $exchangeRate, 2),
                $currency->symbol,
                number_format($value, 2)
            );
        }

        return $currency->symbol . number_format($value, 2);
    }

    private function generateOrderTransactionsHtml($transactions, Currency $currency, Currency $organisationCurrency): string
    {
        if (!$transactions) {
            return '';
        }
        if (is_string($transactions)) {
            $transactions = json_decode($transactions, true);
        }
        $html           = '';
        $currencySymbol = $currency->symbol ?? '£';
        $orgCurrencySymbol = $organisationCurrency->symbol ?? '£';
        $currenciesDiffer = $currency->id !== $organisationCurrency->id;

        foreach ($transactions as $transaction) {
            $historicAsset = $transaction->historicAsset;
            $product = $transaction->model;
            $productImage = Arr::get($product?->imageSources(200, 200), 'original', '');
            $productLink = route('grp.org.shops.show.catalogue.products.current_products.show', [
                $transaction->organisation?->slug,
                $transaction->shop?->slug,
                $product?->slug
            ]);

            $offerData = $transaction?->offers_data;

            // Generate price display based on currency difference
            $priceDisplay = '';
            $quantity = $transaction->quantity_ordered ?? 1;

            if ($currenciesDiffer) {
                // Show both currencies with organisation currency above
                if ($offerData && isset($offerData['o'])) {
                    $originalPrice = ($product->price ?? 0) * $quantity;

                    $orgOriginalPrice = number_format($originalPrice * $transaction->org_exchange, 2);
                    $orgDiscountedPrice = number_format($transaction->net_amount * $transaction->org_exchange, 2);

                    // With discount - show strikethrough original price
                    $priceDisplay = sprintf(
                        '<div style="text-align: right;">
                            <div style="font-size: 16px; font-weight: bold;">
                                <span style="text-decoration: line-through; margin-right: 4px; font-size: 10px;">%s%s</span>
                                %s%s
                            </div>
                            <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                <span style="text-decoration: line-through; margin-right: 4px; font-size: 10px;">%s%s</span>
                                %s%s
                            </div>
                        </div>',
                        $orgCurrencySymbol,
                        $orgOriginalPrice,
                        $orgCurrencySymbol,
                        $orgDiscountedPrice,
                        $currencySymbol,
                        number_format($originalPrice, 2),
                        $currencySymbol,
                        number_format($transaction->net_amount, 2)
                    );
                } else {

                    // Without discount - normal display
                    $priceDisplay = sprintf(
                        '<div style="text-align: right;">
                            <div style="font-size: 16px; font-weight: bold;">%s%s</div>
                            <div style="font-size: 11px; color: #666; margin-top: 2px;">%s%s</div>
                        </div>',
                        $orgCurrencySymbol,
                        number_format($transaction->org_net_amount, 2),
                        $currencySymbol,
                        number_format($transaction->net_amount, 2)
                    );
                }
            } else {
                // Show single currency
                if ($offerData && isset($offerData['o'])) {
                    // With discount - show strikethrough original price
                    $priceDisplay = sprintf(
                        '<div style="text-align: right;">
                            <span style="text-decoration: line-through; margin-right: 4px; color: #666;">%s%s</span>
                            %s%s
                        </div>',
                        $currencySymbol,
                        number_format(($product->price ?? 0) * $quantity, 2),
                        $currencySymbol,
                        number_format($transaction->net_amount, 2)
                    );
                } else {
                    // Without discount - normal display
                    $priceDisplay = $currencySymbol . number_format($transaction->net_amount, 2);
                }
            }

            // Generate discount label if offer data exists
            $discountLabel = '';
            if ($offerData && isset($offerData['o'])) {
                $percentage = $offerData['o']['p'] ?? '';
                $label = $offerData['o']['l'] ?? '';

                // Clean percentage format (same logic as formatPercentage in Vue)
                $cleanPercentage = preg_replace('/\.0%$/', '%', $percentage);

                $discountLabel = sprintf(
                    '<br/>
                    <div style="
                        background-color: rgba(34, 197, 94, 0.1);
                        padding: 2px 4px;
                        font-size: 11px;
                        border: 1px solid rgba(34, 197, 94, 0.3);
                        border-radius: 3px;
                        display: inline-block;
                        color: #15803d;
                        margin-top: 4px;
                        font-weight: bold;
                        max-width: 300px; 
                        white-space: normal; 
                        word-break: break-word;
                    ">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; margin-right: 2px; vertical-align: -2px;">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14.665 3.04046C13.147 1.68411 10.8526 1.68411 9.3346 3.04046L9.09292 3.25641C8.75513 3.55824 8.32487 3.73646 7.87259 3.76189L7.54899 3.78008C5.51656 3.89435 3.8941 5.5168 3.77983 7.54924L3.76164 7.87283C3.73621 8.32512 3.558 8.75537 3.25616 9.09316L3.04021 9.33485C1.68386 10.8528 1.68386 13.1473 3.04021 14.6652L3.25616 14.9069C3.558 15.2447 3.73621 15.675 3.76164 16.1273L3.77983 16.4508C3.8941 18.4833 5.51656 20.1057 7.54899 20.22L7.87259 20.2382C8.32487 20.2636 8.75513 20.4418 9.09292 20.7437L9.3346 20.9596C10.8526 22.316 13.147 22.316 14.665 20.9596L14.9067 20.7437C15.2445 20.4418 15.6747 20.2636 16.127 20.2382L16.4506 20.22C18.483 20.1057 20.1055 18.4833 20.2198 16.4509L20.238 16.1273C20.2634 15.675 20.4416 15.2447 20.7434 14.9069L20.9594 14.6652C22.3157 13.1473 22.3157 10.8528 20.9594 9.33484L20.7434 9.09316C20.4416 8.75537 20.2634 8.32512 20.238 7.87283L20.2198 7.54924C20.1055 5.5168 18.483 3.89435 16.4506 3.78008L16.127 3.76189C15.6747 3.73646 15.2445 3.55824 14.9067 3.25641L14.665 3.04046ZM15.7071 8.29289C16.0976 8.68342 16.0976 9.31658 15.7071 9.70711L9.70711 15.7071C9.31658 16.0976 8.68342 16.0976 8.29289 15.7071C7.90237 15.3166 7.90237 14.6834 8.29289 14.2929L14.2929 8.29289C14.6834 7.90237 15.3166 7.90237 15.7071 8.29289ZM16 14.5C16 15.3284 15.3284 16 14.5 16C13.6716 16 13 15.3284 13 14.5C13 13.6716 13.6716 13 14.5 13C15.3284 13 16 13.6716 16 14.5ZM9.5 11C10.3284 11 11 10.3284 11 9.5C11 8.67157 10.3284 8 9.5 8C8.67157 8 8 8.67157 8 9.5C8 10.3284 8.67157 11 9.5 11Z"/>
                        </svg>
                        <span style="margin-left: 1px; vertical-align: middle;">%s %s</span>
                    </div>',
                    $cleanPercentage,
                    $label
                );
            }

            $html .= sprintf(
                '<tr style="border-bottom: 1px solid #e9e9e9;">
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: left;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:12px; vertical-align:top;">%s</td>
                            <td style="vertical-align:top;">
                                <span><strong style="color: #555;">%s</strong></span>
                                <br/>
                                <a href="%s"
                                   target="_blank"
                                   style="color:#2563eb;
                                          text-decoration:underline;
                                          font-weight:500;">
                                    %s
                                </a>
                                %s
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: center;">%s</td>
                <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; font-size: 14px; padding: 8px 0; text-align: right;">%s</td>
            </tr>',
                $productImage
                    ? '<img src="' . $productImage . '"
                         width="56"
                         height="56"
                         style="display:block;border-radius:6px;object-fit:cover;" />'
                    : '',
                $historicAsset->code ?? 'N/A',
                $productLink,
                $historicAsset->name ?? 'N/A',
                $discountLabel,
                rtrim(rtrim(sprintf('%.3f', $transaction->quantity_ordered ?? 0), '0'), '.') ?? '0',
                $priceDisplay
            );
        }

        return $html;
    }
}
