<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Zentrada;

use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every other mail TradeSafe sends (cancellations, complaints, payout notices) is kept on the order of
 * the transaction it names, or on the Zentrada customer when no order has it, so nobody copies it in.
 * No order is ever made from one. Anything but a payout notice may need someone to act, so it also
 * gives the person checking Zentrada orders a task.
 */
class SaveZentradaNoticeFromEmail
{
    use AsAction;

    /**
     * Null when there is nothing in the shop to keep it on, so it is left for a person.
     */
    public function handle(Shop $shop, GmailClient $client, string $gmailMessageId, array $raw): Order|Customer|null
    {
        $subject     = (string) GmailMessageParser::header($raw, 'Subject');
        $body        = GmailMessageParser::body($raw);
        $transaction = self::transactionIn($subject) ?? self::transactionIn($body);
        $order       = $transaction ? $this->order($shop, $transaction) : null;
        $model       = $order ?? $this->zentradaCustomer($shop);

        if (!$model) {
            return null;
        }

        $import = ImportZentradaOrder::make();
        $import->attachFiles($model, $this->files($client, $gmailMessageId, $raw, $subject, $body), __('TradeSafe: :subject', ['subject' => Str::limit($subject, 200)]));

        if ($order) {
            $order->update(['internal_notes' => trim($order->internal_notes."\n\n".__('TradeSafe :date: :subject', ['date' => now()->format('d.m.Y'), 'subject' => $subject]))]);
        }

        if (!self::isPayoutNotice($subject)) {
            StoreStaffTask::run(GetAikuAssistant::run($shop->group_id), [
                'subject'     => __('TradeSafe wrote about :about', ['about' => $order ? __('Zentrada order :order (:transaction)', ['order' => $order->reference, 'transaction' => $transaction]) : ($transaction ?? __('a Zentrada transaction'))]),
                'description' => $subject."\n\n".Str::limit($body, 2000),
                ...$import->assignment($shop),
                ...($order ? ['model_type' => 'Order', 'model_id' => $order->id] : []),
                'data'        => array_filter(['organisation_id' => $shop->organisation_id, 'zentrada_transaction' => $transaction]),
            ]);
        }

        return $model;
    }

    /**
     * "Cancellation EU-NL 958559" and "transaction EU-NL958559" both give "EU-NL 958559".
     */
    public static function transactionIn(string $text): ?string
    {
        return preg_match('/\b([A-Z]{2}-[A-Z]{2})\s*(\d{4,})\b/', $text, $match) ? $match[1].' '.$match[2] : null;
    }

    public static function isPayoutNotice(string $subject): bool
    {
        return preg_match('/payout|pay-out|auszahlung|gutschrift|credit note|remittance/i', $subject) === 1;
    }

    private function order(Shop $shop, string $transaction): ?Order
    {
        return Order::where('shop_id', $shop->id)
            ->where(fn ($query) => $query->where('customer_reference', $transaction)->orWhere('external_id', $transaction))
            ->first();
    }

    private function zentradaCustomer(Shop $shop): ?Customer
    {
        $reference = Arr::get($shop->settings, 'zentrada.customer_reference');

        return $reference ? Customer::where('shop_id', $shop->id)->where('reference', $reference)->first() : null;
    }

    /**
     * @return list<array{name: string, contents: string}>
     */
    private function files(GmailClient $client, string $gmailMessageId, array $raw, string $subject, string $body): array
    {
        $files = [['name' => (Str::slug(Str::limit($subject, 80, '')) ?: 'tradesafe-email').'.txt', 'contents' => $subject."\n\n".$body]];

        foreach (GmailMessageParser::attachments(Arr::get($raw, 'payload', [])) as $attachment) {
            if ($attachment['inline']) {
                continue;
            }

            $files[] = [
                'name'     => $attachment['filename'],
                'contents' => $attachment['data'] ? GmailMessageParser::decodeData($attachment['data']) : $client->getAttachment($gmailMessageId, (string) $attachment['attachmentId']),
            ];
        }

        return $files;
    }
}
