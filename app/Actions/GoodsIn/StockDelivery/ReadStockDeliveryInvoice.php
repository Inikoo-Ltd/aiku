<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use RuntimeException;
use Throwable;

/**
 * Reads a supplier or agent invoice attached to a delivery into its lines, charges and total. Two models of
 * different makers read it apart, and what they read is checked against the text of the document, as for the
 * service invoices. The reading is kept on the attachment and only ever proposed: nothing is costed until someone
 * reviews and applies it, and its amounts cannot be changed on the way.
 */
class ReadStockDeliveryInvoice extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    use WithAICreditErrorHandler;
    use WithAIGateway;
    use WithStockDeliveryDocument;

    public int $jobTimeout = 600;

    public const string KIND = 'supplier_invoice';

    private const array KEYS = ['is_invoice', 'issuer', 'invoice_number', 'invoice_date', 'currency', 'references', 'lines', 'charges', 'total'];

    public function handle(StockDelivery $stockDelivery, Media $media): void
    {
        try {
            $reading = ['state' => 'read', 'kind' => self::KIND, 'read_at' => now()->toIso8601String(), ...$this->read($stockDelivery, $media)];
        } catch (Throwable $exception) {
            report($exception);
            $reading = [
                'state' => 'failed',
                'kind'  => self::KIND,
                'error' => $this->isAICreditThrowable($exception)
                    ? __('The AI service has run out of credit.')
                    : __('The invoice could not be read.'),
            ];
        }

        DB::transaction(function () use ($stockDelivery, $media, $reading) {
            self::pivot($stockDelivery, $media)->lockForUpdate()->first();

            if (!data_get(self::reading($stockDelivery, $media), 'applied_at')) {
                self::storeReading($stockDelivery, $media, $reading);
            }
        });
    }

    public static function pivot(StockDelivery $stockDelivery, Media $media): \Illuminate\Database\Query\Builder
    {
        return DB::table('model_has_attachments')
            ->where('model_type', 'StockDelivery')
            ->where('model_id', $stockDelivery->id)
            ->where('media_id', $media->id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function reading(StockDelivery $stockDelivery, Media $media): ?array
    {
        $data = json_decode((string) self::pivot($stockDelivery, $media)->value('data'), true);

        return Arr::get($data ?? [], 'invoice_reading');
    }

    /**
     * @param  array<string, mixed>  $reading
     */
    public static function storeReading(StockDelivery $stockDelivery, Media $media, array $reading): void
    {
        $data                    = json_decode((string) self::pivot($stockDelivery, $media)->value('data'), true) ?: [];
        $data['invoice_reading'] = $reading;

        self::pivot($stockDelivery, $media)->update(['data' => json_encode($data)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(StockDelivery $stockDelivery, Media $media): array
    {
        $content = (string) stream_get_contents($media->stream());
        $readers = array_values(array_filter((array) config('services.stock_delivery_documents.readers')));

        if (count($readers) < 2) {
            throw new RuntimeException('An invoice is read by two models, set services.stock_delivery_documents.readers');
        }

        $expected = $stockDelivery->items()
            ->with(['supplierProduct:id,code,name', 'orgStock:id,code'])
            ->get()
            ->map(fn (StockDeliveryItem $item) => $item->supplierProduct?->code.' / '.$item->orgStock?->code.' — '.$item->supplierProduct?->name)
            ->filter()
            ->implode("\n");

        [$first, $second] = array_map(fn (string $model) => $this->ask($model, $media, $content, $expected), array_slice($readers, 0, 2));

        $text          = $this->documentText($media, $content);
        $textAvailable = StockDeliveryDocumentChecks::hasText($text);
        $references    = array_values(array_unique([...$first['references'], ...$second['references']]));
        $haystack      = $textAvailable ? $text : implode("\n", $references);
        $amounts       = array_filter(
            [
                ...array_column($first['lines'], 'amount'),
                ...array_column($first['lines'], 'quantity'),
                ...array_map(fn (array $charge) => abs((float) $charge['amount']), $first['charges']),
                $first['total'],
            ],
            fn ($amount) => $amount !== null && (float) $amount != 0.0
        );

        return [
            ...$first,
            'issuer'     => $first['issuer'] ?? $second['issuer'],
            'lines'      => self::agreedBarcodes($first['lines'], $second['lines']),
            'references' => $references,
            'readers'    => array_slice($readers, 0, 2),
            'checks'     => [
                'disagreements'     => StockDeliveryDocumentChecks::disagreements($first, $second),
                'text_available'    => $textAvailable,
                'amounts_missing'   => $textAvailable
                    ? array_values(array_unique(array_filter($amounts, fn ($amount) => !StockDeliveryDocumentChecks::amountInText((float) $amount, $text))))
                    : [],
                'mentions_delivery' => $this->mentionsDelivery($stockDelivery, $haystack),
                'currency_printed'  => $textAvailable && $first['currency'] ? StockDeliveryDocumentChecks::currencyInText($first['currency'], $text) : null,
                'number_printed'    => $textAvailable && $first['invoice_number']
                    ? str_contains(StockDeliveryDocumentChecks::normalise($text), StockDeliveryDocumentChecks::normalise($first['invoice_number']))
                    : null,
                'other_deliveries'  => StockDeliveryDocumentChecks::otherDeliveriesMentioned($stockDelivery, $haystack),
            ],
        ];
    }

    /**
     * A line keeps its barcode only when both readers read the same one, since a barcode decides which product the
     * line is costed on. Spreadsheets print long numbers as 5.05637E+12, which is no barcode at all.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, array<string, mixed>>  $otherLines
     * @return array<int, array<string, mixed>>
     */
    private static function agreedBarcodes(array $lines, array $otherLines): array
    {
        $key       = fn (array $line) => StockDeliveryDocumentChecks::normalise($line['code']).'|'.round((float) $line['quantity'], 4).'|'.(int) round((float) $line['amount'] * 100);
        $remaining = collect($otherLines)->groupBy($key)->map(fn ($same) => $same->pluck('barcode')->all())->all();

        foreach ($lines as &$line) {
            $theirs          = isset($remaining[$key($line)]) ? array_shift($remaining[$key($line)]) : null;
            $line['barcode'] = self::barcode($line['barcode']) !== null && self::barcode($line['barcode']) === self::barcode($theirs) ? self::barcode($line['barcode']) : null;
        }

        return $lines;
    }

    public static function barcode(?string $barcode): ?string
    {
        $barcode = trim((string) $barcode);

        return $barcode === '' || preg_match('/e[+-]?\d/i', $barcode) ? null : (StockDeliveryDocumentChecks::normalise($barcode) ?: null);
    }

    /**
     * A supplier writes our purchase order number on its invoice more often than the delivery's, so either names it.
     */
    private function mentionsDelivery(StockDelivery $stockDelivery, string $haystack): ?bool
    {
        $mentions = collect([$stockDelivery->reference, ...$stockDelivery->purchaseOrders()->pluck('reference')->all()])
            ->map(fn (string $reference) => StockDeliveryDocumentChecks::mentions($reference, $haystack));

        return $mentions->containsStrict(true) ? true : ($mentions->containsStrict(false) ? false : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function ask(string $model, Media $media, string $content, string $expected): array
    {
        $prompt = 'Read this supplier invoice and return its content as JSON.'
            ."\nCopy every number and product code exactly as printed. Leave a field null when the document does not show it: never guess, round, convert or calculate a missing number."
            ."\nAn amount printed as a dash (-) is 0."
            ."\nlines are the goods, one per printed line, with the amount of the line before tax; charges are anything else billed (freight, packing, bank fees, samples, discounts as negative amounts)."
            ."\ntotal is the amount payable before tax. Dates as YYYY-MM-DD, currency as the ISO 4217 code printed on the document."
            ."\nbarcode is the EAN or barcode printed on the line, copied exactly, null when the line shows none."
            ."\nreferences are every order, purchase order, shipment, container and bill of lading number printed on it, copied exactly."
            ."\nissuer is the company that issued the invoice, invoice_number its number."
            ."\nSet is_invoice to false when the document is not an invoice or proforma for goods."
            ."\nFor reference, the products we expect on it (supplier code / our code — name):\n".$expected;

        $response = $this->aiRequest()
            ->timeout(240)
            ->post('chat/completions', [
                'model'           => $this->aiModel($model),
                'messages'        => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt], $this->documentPart($media, $content)]]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'supplier_invoice', 'strict' => true, 'schema' => self::schema()]],
            ]);

        $this->guardAICreditResponse($response, 'grp');

        $response->throw();

        $reading = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($reading) || array_diff(self::KEYS, array_keys($reading)) || !is_array($reading['lines']) || !is_array($reading['charges']) || !is_array($reading['references'])) {
            throw new RuntimeException("$model did not return a supplier invoice reading");
        }

        $number = fn ($value) => is_numeric($value) ? (float) $value : null;

        $reading['lines'] = array_map(fn ($line) => [
            'code'        => isset($line['code']) ? (string) $line['code'] : null,
            'barcode'     => isset($line['barcode']) ? (string) $line['barcode'] : null,
            'description' => $line['description'] ?? null,
            'quantity'    => $number($line['quantity'] ?? null),
            'unit_price'  => $number($line['unit_price'] ?? null),
            'amount'      => $number($line['amount'] ?? null),
        ], $reading['lines']);
        $reading['charges'] = array_map(fn ($charge) => [
            'label'  => (string) ($charge['label'] ?? ''),
            'amount' => (float) ($charge['amount'] ?? 0),
        ], $reading['charges']);
        $reading['total']      = $number($reading['total']);
        $reading['references'] = array_values(array_filter(array_map('strval', $reading['references'])));
        $reading['currency']   = StockDeliveryDocumentChecks::currencyCode($reading['currency'] ?? null);

        return array_intersect_key($reading, array_flip(self::KEYS));
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => self::KEYS,
            'properties'           => [
                'is_invoice'     => ['type' => 'boolean'],
                'issuer'         => $nullableString,
                'invoice_number' => $nullableString,
                'invoice_date'   => $nullableString,
                'currency'       => $nullableString,
                'references'     => ['type' => 'array', 'items' => ['type' => 'string']],
                'total'          => $nullableNumber,
                'lines'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['code', 'barcode', 'description', 'quantity', 'unit_price', 'amount'],
                        'properties'           => [
                            'code'        => $nullableString,
                            'barcode'     => $nullableString,
                            'description' => $nullableString,
                            'quantity'    => $nullableNumber,
                            'unit_price'  => $nullableNumber,
                            'amount'      => $nullableNumber,
                        ],
                    ],
                ],
                'charges'        => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['label', 'amount'],
                        'properties'           => [
                            'label'  => ['type' => 'string'],
                            'amount' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function asController(StockDelivery $stockDelivery, Media $media, ActionRequest $request): void
    {
        $this->initialisation($stockDelivery->organisation, $request);

        abort_unless(self::pivot($stockDelivery, $media)->exists(), 404);
        abort_if((bool) data_get(self::reading($stockDelivery, $media), 'applied_at'), 422);

        self::queue($stockDelivery, $media);
    }

    public static function queue(StockDelivery $stockDelivery, Media $media): void
    {
        self::storeReading($stockDelivery, $media, ['state' => 'reading', 'kind' => self::KIND]);

        self::dispatch($stockDelivery, $media);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
