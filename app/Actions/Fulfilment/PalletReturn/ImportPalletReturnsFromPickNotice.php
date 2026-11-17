<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\StoredItem\AllocateStoredItemToReturn;
use App\Exceptions\Fulfilment\PalletReturnStockShortException;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A fulfilment customer's own system emails a "Pick Notice" listing several orders. Each order
 * becomes a stored item collection return, referenced the way staff typed them by hand:
 * "{order} {surname} {postcode}". Senders and product code aliases live on the fulfilment
 * customer: data.pick_notice = {senders: [...], aliases: {"TG46SE-2": "TG46SE"}}.
 */
class ImportPalletReturnsFromPickNotice
{
    use AsAction;

    /**
     * A reply or forward quotes the notice but is a person writing; it goes to the inbox.
     */
    public static function isPickNoticeSubject(?string $subject): bool
    {
        return $subject !== null
            && stripos($subject, 'pick notice') !== false
            && ! preg_match('/^\s*(re|fwd?|fw)\s*:/i', $subject);
    }

    public static function fulfilmentCustomerForSender(Shop $shop, ?string $address): ?FulfilmentCustomer
    {
        if (! $address || ! $shop->fulfilment) {
            return null;
        }

        return FulfilmentCustomer::where('fulfilment_id', $shop->fulfilment->id)
            ->whereJsonContains('data->pick_notice->senders', strtolower($address))
            ->first();
    }

    /**
     * Null when the mail is not a pick notice that could be read in full, so it reaches the
     * inbox for a person instead of being half imported.
     *
     * @return array<int, PalletReturn>|null
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, string $html, ?string $gmailMessageId = null): ?array
    {
        $orders = self::parse($html);

        if ($orders === null) {
            return null;
        }

        $storedItems = $this->storedItemsByCode($fulfilmentCustomer);
        $created     = [];

        // ponytail: compared in PHP, the reference column's collation refuses LIKE; fine for a few thousand returns
        $enteredOrders = $fulfilmentCustomer->palletReturns()->whereNotNull('customer_reference')->pluck('customer_reference')
            ->map(fn (string $reference) => strtok($reference, ' '))
            ->flip();

        foreach ($orders as $order) {
            if ($enteredOrders->has($order['order'])) {
                continue;
            }

            $created[] = DB::transaction(fn () => $this->storeReturn($fulfilmentCustomer, $order, $storedItems, $gmailMessageId));
        }

        return $created;
    }

    /**
     * @param array{order: string, customer_reference: string, items: array<int, array{code: string, quantity: float}>} $order
     * @param array<string, StoredItem> $storedItems
     */
    private function storeReturn(FulfilmentCustomer $fulfilmentCustomer, array $order, array $storedItems, ?string $gmailMessageId): PalletReturn
    {
        $palletReturn = StorePalletReturn::make()->actionWithStoredItems($fulfilmentCustomer, [
            'is_collection' => true,
            'data'          => ['pick_notice' => ['order' => $order['order'], 'gmail_message_id' => $gmailMessageId]],
        ]);

        $palletReturn = UpdatePalletReturn::make()->action($palletReturn, ['customer_reference' => $order['customer_reference']]);

        $problems = [];
        foreach ($order['items'] as $item) {
            $storedItem = $storedItems[self::normaliseCode($item['code'])] ?? null;

            if (! $storedItem) {
                $problems[] = "product code {$item['code']} is not one of the customer's stored items";
                continue;
            }

            if (AllocateStoredItemToReturn::run($palletReturn, $storedItem, $item['quantity'])->isEmpty()) {
                $problems[] = "no stock of {$storedItem->reference} for {$item['quantity']}";
            }
        }

        if ($problems === []) {
            try {
                return SubmitAndConfirmPalletReturn::run($palletReturn->refresh());
            } catch (PalletReturnStockShortException) {
                $problems[] = 'not enough stock to submit';
            }
        }

        return UpdatePalletReturn::make()->action($palletReturn->refresh(), [
            'internal_notes' => 'Pick notice: '.implode('; ', $problems),
        ]);
    }

    /**
     * @return array<string, StoredItem>
     */
    private function storedItemsByCode(FulfilmentCustomer $fulfilmentCustomer): array
    {
        $storedItems = [];
        foreach ($fulfilmentCustomer->storedItems()->get() as $storedItem) {
            if (stripos($storedItem->reference, 'do not use') !== false) {
                continue;
            }

            $storedItems[self::normaliseCode($storedItem->reference)] = $storedItem;
        }

        foreach (Arr::get($fulfilmentCustomer->data, 'pick_notice.aliases', []) as $code => $reference) {
            $storedItem = $storedItems[self::normaliseCode($reference)] ?? null;
            if ($storedItem) {
                $storedItems[self::normaliseCode($code)] = $storedItem;
            }
        }

        return $storedItems;
    }

    public static function normaliseCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $code));
    }

    /**
     * Reads the notice as lines of text rather than by its markup, so a change of layout that
     * keeps the labels still parses. Null when the order count it announces is not what was read.
     *
     * @return array<int, array{order: string, customer_reference: string, items: array<int, array{code: string, quantity: float}>}>|null
     */
    public static function parse(string $html): ?array
    {
        $lines = self::lines($html);

        $blocks = [];
        foreach ($lines as $line) {
            if (preg_match('/^Pick Notice\s*-\s*#\s*(\S+)$/i', $line, $match)) {
                $blocks[] = ['order' => $match[1], 'lines' => []];
            } elseif ($blocks !== []) {
                $blocks[array_key_last($blocks)]['lines'][] = $line;
            }
        }

        if ($blocks === []) {
            return null;
        }

        $orders = [];
        foreach ($blocks as $block) {
            $name     = self::labelValue($block['lines'], 'Customer Name')[0] ?? null;
            $address  = self::labelValue($block['lines'], 'Delivery Address');
            $items    = self::items($block['lines']);
            $surname  = $name ? trim(Arr::last(preg_split('/\s+/', $name)), '.,') : null;
            $postcode = Arr::last($address);

            if (! $surname || ! $postcode || $items === []) {
                return null;
            }

            $orders[] = [
                'order'              => $block['order'],
                'customer_reference' => "{$block['order']} $surname $postcode",
                'items'              => $items,
            ];
        }

        $announced = null;
        foreach ($lines as $line) {
            if (preg_match('/^(\d+) orders? to pick$/i', $line, $match)) {
                $announced = (int) $match[1];
            }
        }

        if ($announced !== null && $announced !== count($orders)) {
            return null;
        }

        return $orders;
    }

    /**
     * @return array<int, string>
     */
    private static function lines(string $html): array
    {
        $html = preg_replace('/<(style|script|head)\b.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(br|\/(td|th|tr|p|div|h[1-6]|li|table|span))\b[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(
            array_map(fn (string $line) => trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $line)), explode("\n", $text)),
            fn (string $line) => $line !== ''
        ));
    }

    /**
     * The value of "Label:", on the same line or on the lines after it up to the next label.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private static function labelValue(array $lines, string $label): array
    {
        foreach ($lines as $index => $line) {
            if (stripos($line, $label.':') !== 0) {
                continue;
            }

            $rest = trim(substr($line, strlen($label) + 1));
            if ($rest !== '') {
                return [$rest];
            }

            $values = [];
            foreach (array_slice($lines, $index + 1) as $next) {
                if (preg_match('/^[A-Za-z][A-Za-z ]*:/', $next) || in_array(strtolower($next), ['items', 'customer details'], true)) {
                    break;
                }
                $values[] = $next;
            }

            return $values;
        }

        return [];
    }

    /**
     * Rows after the "Product Code / Description / Qty" header: the first cell is the code and a
     * whole number closes the row.
     *
     * @param array<int, string> $lines
     * @return array<int, array{code: string, quantity: float}>
     */
    private static function items(array $lines): array
    {
        $header = array_search('qty', array_map('strtolower', $lines), true);

        if ($header === false) {
            return [];
        }

        $items = [];
        $row   = [];
        foreach (array_slice($lines, $header + 1) as $line) {
            if (preg_match('/^\d+$/', $line) && $row !== []) {
                $items[] = ['code' => $row[0], 'quantity' => (float) $line];
                $row     = [];
            } else {
                $row[] = $line;
            }
        }

        return $items;
    }
}
