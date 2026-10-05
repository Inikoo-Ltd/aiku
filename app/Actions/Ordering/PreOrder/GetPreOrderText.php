<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Every pre-order text a customer reads (HELP-3678). Customers only ever see "Pre-order": back-order
 * and made-to-order stay inside aiku, where they set the payment rules. Each text can be rewritten
 * per shop language in the shop settings (settings.pre_orders.texts.{locale}.{key}); an empty one
 * falls back to the default below, translated. {placeholders} are filled in here, the shop's
 * pre-order settings always, the rest from the caller.
 */
class GetPreOrderText
{
    use AsObject;

    /**
     * @var array<int, string>|null
     */
    private static ?array $languageCodes = null;

    /**
     * @var array<string, array{0: string, 1: string}> key => [what staff see in the settings, default text]
     */
    public const array TEXTS = [
        'label'                         => ['Name', 'Pre-order'],
        'available'                     => ['Out of stock, can be pre-ordered', 'Available to pre-order'],
        'dispatch'                      => ['Estimated dispatch', 'Estimated dispatch {weeks} weeks'],
        'pay_in_full'                   => ['Payment: in full', 'Pay in full now'],
        'pay_deposit'                   => ['Payment: deposit', '{deposit_percent}% deposit now, balance when the goods arrive'],
        'terms_link'                    => ['Terms link', 'Pre-order terms'],
        'basket_line'                   => ['Basket line, all pre-ordered', '{pre_order} pre-ordered (estimated dispatch {weeks} weeks)'],
        'basket_line_partial'           => ['Basket line, partly in stock', '{in_stock} will be sent now, {pre_order} are pre-ordered (estimated dispatch {weeks} weeks)'],
        'checkout_mixed'                => ['Checkout, in-stock and pre-order items', 'We\'ll send your in-stock items now and your pre-order items as soon as they arrive.'],
        'checkout_hold_together'        => ['Checkout, hold everything option', 'Hold my order and send everything together'],
        'checkout_only_pre_order'       => ['Checkout, only pre-order items', 'We\'ll dispatch your order as soon as the goods arrive.'],
        'checkout_accept'               => ['Checkout, tick box', 'I accept the estimated dispatch time and the pre-order terms above'],
        'terms_estimate'                => ['Terms: estimate', 'The dispatch time is an estimate, not a guaranteed date.'],
        'terms_paid_in_full'            => ['Terms: paid in full (trade)', 'Pre-order items paid in full at checkout can be cancelled before dispatch for a full refund: just contact us.'],
        'terms_paid_in_full_retail'     => ['Terms: paid in full (retail)', 'Pre-order items are paid in full at checkout, and the payment is not refundable once the order is placed.'],
        'terms_deposit'                 => ['Terms: deposit', 'Pre-order items with a deposit: {deposit_percent}% is paid at checkout (orders under {full_payment_below} are paid in full). The balance is requested by email when the goods reach our warehouse and is due within {balance_due_days} days; if it is not paid within {balance_cancel_days} days the order is cancelled and the deposit kept.'],
        'terms_deposit_cancel'          => ['Terms: cancelling items with a deposit', 'To cancel pre-order items with a deposit, contact us. Within {free_cancellation_days} working days of ordering, and until we place the order with our supplier, it is free of charge. After that the deposit is not refunded.'],
        'terms_late'                    => ['Terms: late or not supplied', 'If our supplier cannot supply, or we are more than {late_cancellation_days} days past the estimated dispatch, contact us to cancel for a full refund.'],
        'terms_handmade'                => ['Terms: handmade items', 'Handmade items vary in size, colour, grain and finish, and the photos show a typical example. Dimensions are approximate.'],
        'terms_pallet'                  => ['Terms: pallet delivery', 'Pallet delivery is to the kerb only; you must be able to unload heavy items.'],
        'line_note'                     => ['Order confirmation and invoice line', 'Pre-order · Estimated dispatch {weeks} weeks'],
        'order_dispatch_dates'          => ['Order: estimated dispatch dates', 'Estimated dispatch between {from_date} and {to_date}'],
        'order_balance_due'             => ['Order confirmation: amount still to pay', 'Balance due when the goods arrive'],
        'order_split'                   => ['Order confirmation: in-stock order', 'Your pre-order items are in order {pre_order_number}, which is sent separately when they arrive.'],
        'order_split_pre_order'         => ['Order confirmation: pre-order order', 'The in-stock items of your order are in order {in_stock_order_number}, sent now.'],
        'order_cancel_contact'          => ['Order: how to cancel', 'To cancel this pre-order, please contact us.'],
        'email_greeting'                => ['Emails: greeting', 'Hello {customer_name},'],
        'email_pay_button'              => ['Emails: pay button', 'Pay the balance'],
        'email_view_button'             => ['Emails: view order button', 'View the order'],
        'email_balance_request_subject' => ['Balance request email: subject', 'Your pre-order {order_number} has arrived: balance due'],
        'email_balance_request_body'    => ['Balance request email: text', "Good news: the goods for your pre-order {order_number} have reached our warehouse.\n\nThe balance of {amount} is due by {balance_due_date}. Please pay it with the link below and we will send your order.\n\nIf it is not paid within {balance_cancel_days} days of this email, the order is cancelled and the deposit is kept."],
        'email_pallet_quote'            => ['Balance request email: pallet cost', 'The pallet delivery costs {pallet_quote} (the estimate was {pallet_estimate}).'],
        'email_pallet_over_tolerance'   => ['Balance request email: pallet cost above estimate', 'As this is more than {pallet_tolerance_percent}% above the estimate, you can cancel the order and get your deposit back. Just reply to this email.'],
        'email_balance_reminder_subject' => ['Balance reminder email: subject', 'Reminder: balance due for pre-order {order_number}'],
        'email_balance_reminder_body'   => ['Balance reminder email: text', "The balance of {amount} for your pre-order {order_number} is due by {balance_due_date}.\n\nIf it is not paid by {cancel_date}, the order is cancelled and the deposit is kept."],
        'email_dispatch_changed_subject' => ['Date change email: subject', 'New estimated dispatch for pre-order {order_number}'],
        'email_dispatch_changed_body'   => ['Date change email: text (our message to the customer goes after it)', "The estimated dispatch of your pre-order {order_number} has changed. It is now expected between {from_date} and {to_date}.\n\nTo cancel the order, contact us: the refund is as set out in the pre-order terms. If we are more than {late_cancellation_days} days past the estimated dispatch, you get a full refund, deposit included."],
        'email_cancelled_subject'       => ['Cancellation email: subject', 'Pre-order {order_number} cancelled'],
        'email_cancelled_body'          => ['Cancellation email: text (our reason goes after it)', 'Your pre-order {order_number} has been cancelled.'],
        'email_cancelled_refund'        => ['Cancellation email: refund', '{amount} has been returned to your account balance.'],
        'email_cancelled_no_refund'     => ['Cancellation email: no refund', 'No refund is due under the pre-order terms.'],
    ];

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public function handle(Shop $shop, string $key, array $values = [], ?string $locale = null): string
    {
        $locale ??= $this->locale($shop);

        $text = trim((string) Arr::get($shop->settings, "pre_orders.texts.$locale.$key"));
        if ($text === '') {
            $text = $this->default($key, $locale);
        }

        return strtr($text, $this->placeholders($shop, $values));
    }

    private function locale(Shop $shop): string
    {
        $appLocale = app()->getLocale();
        if ($appLocale == $shop->language->code || !$shop->extra_languages) {
            return $shop->language->code;
        }

        return in_array($appLocale, $this->locales($shop)) ? $appLocale : $shop->language->code;
    }

    public function default(string $key, ?string $locale = null): string
    {
        return __(self::TEXTS[$key][1], [], $locale);
    }

    /**
     * Paragraphs are separated by an empty line.
     *
     * @param  array<string, string|int|float|null>  $values
     *
     * @return array<int, string>
     */
    public function paragraphs(Shop $shop, string $key, array $values = []): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', $this->handle($shop, $key, $values)))));
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     *
     * @return array<string, string>
     */
    private function placeholders(Shop $shop, array $values): array
    {
        $placeholders = [];
        foreach (array_merge($this->shopValues($shop), $values) as $name => $value) {
            $placeholders['{'.$name.'}'] = (string) $value;
        }

        return $placeholders;
    }

    /**
     * @return array<string, string>
     */
    private function shopValues(Shop $shop): array
    {
        return [
            'deposit_percent'          => trimDecimalZeros($shop->preOrderSetting('deposit_percentage')),
            'full_payment_below'       => $shop->currency->symbol.trimDecimalZeros($shop->preOrderSetting('full_payment_below')),
            'balance_due_days'         => (string) $shop->preOrderSetting('balance_due_days'),
            'balance_cancel_days'      => (string) $shop->preOrderSetting('balance_cancel_after_days'),
            'free_cancellation_days'   => (string) $shop->preOrderSetting('free_cancellation_working_days'),
            'late_cancellation_days'   => (string) $shop->preOrderSetting('late_cancellation_days'),
            'pallet_tolerance_percent' => trimDecimalZeros($shop->preOrderSetting('pallet_quote_tolerance_percentage')),
        ];
    }

    public function weeks(int $from, int $to): string
    {
        return $from == $to ? (string) $from : $from.'–'.$to;
    }

    /**
     * @return array<int, string>
     */
    public function locales(Shop $shop): array
    {
        $languageCodes = $shop->extra_languages ? $this->languageCodes() : [];
        $extraLocales  = array_filter(array_map(fn ($languageId) => $languageCodes[(int) $languageId] ?? null, $shop->extra_languages ?? []));

        return array_values(array_unique(array_merge([$shop->language->code], $extraLocales)));
    }

    /**
     * @return array<int, string>
     */
    private function languageCodes(): array
    {
        return self::$languageCodes ??= Language::pluck('code', 'id')->all();
    }
}
