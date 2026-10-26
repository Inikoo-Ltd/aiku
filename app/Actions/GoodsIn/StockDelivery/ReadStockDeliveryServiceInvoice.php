<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use RuntimeException;
use Throwable;

/**
 * Reads the invoice of a forwarder, customs broker, haulier or port attached to a delivery. Two models of
 * different makers read it apart, and what they read is checked against the text of the document; the reading
 * is kept on the attachment and only ever proposed. Its amounts cannot be changed on the way to the costing:
 * a reading that fails a check is entered by hand.
 */
class ReadStockDeliveryServiceInvoice extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    use WithAICreditErrorHandler;
    use WithAIGateway;
    use WithStockDeliveryDocument;

    public int $jobTimeout = 600;

    public const string KIND = 'service_invoice';

    private const array KEYS = ['is_invoice', 'issuer', 'invoice_number', 'invoice_date', 'currency', 'references', 'lines', 'net_total', 'vat_total', 'total_payable'];

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
            ReadStockDeliveryInvoice::pivot($stockDelivery, $media)->lockForUpdate()->first();

            if (!self::isApplied($stockDelivery, $media)) {
                ReadStockDeliveryInvoice::storeReading($stockDelivery, $media, $reading);
            }
        });
    }

    public static function isApplied(StockDelivery $stockDelivery, Media $media): bool
    {
        return (bool) data_get(ReadStockDeliveryInvoice::reading($stockDelivery, $media), 'applied_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function read(StockDelivery $stockDelivery, Media $media): array
    {
        $content = (string) stream_get_contents($media->stream());
        $readers = array_values(array_filter((array) config('services.stock_delivery_documents.readers')));

        if (count($readers) < 2) {
            throw new RuntimeException('A service invoice is read by two models, set services.stock_delivery_documents.readers');
        }

        [$first, $second] = array_map(fn (string $model) => $this->ask($model, $media, $content), array_slice($readers, 0, 2));

        $text          = $this->documentText($media, $content);
        $textAvailable = StockDeliveryDocumentChecks::hasText($text);
        $references    = array_values(array_unique([...$first['references'], ...$second['references']]));
        $haystack      = $textAvailable ? $text : implode("\n", $references);
        $amounts       = array_filter(
            [...array_column($first['lines'], 'amount'), $first['net_total'], $first['vat_total'], $first['total_payable']],
            fn ($amount) => $amount !== null && (float) $amount != 0.0
        );

        return [
            ...$first,
            'issuer'     => $first['issuer'] ?? $second['issuer'],
            'lines'      => $this->agreedLines($first['lines'], $second['lines']),
            'references' => $references,
            'readers'    => array_slice($readers, 0, 2),
            'checks'     => [
                'disagreements'     => StockDeliveryDocumentChecks::disagreements($first, $second),
                'text_available'    => $textAvailable,
                'amounts_missing'   => $textAvailable
                    ? array_values(array_unique(array_filter($amounts, fn ($amount) => !StockDeliveryDocumentChecks::amountInText((float) $amount, $text))))
                    : [],
                'mentions_delivery' => StockDeliveryDocumentChecks::mentions($stockDelivery->reference, $haystack),
                'currency_printed'  => $textAvailable && $first['currency'] ? StockDeliveryDocumentChecks::currencyInText($first['currency'], $text) : null,
                'number_printed'    => $textAvailable && $first['invoice_number']
                    ? str_contains(StockDeliveryDocumentChecks::normalise($text), StockDeliveryDocumentChecks::normalise($first['invoice_number']))
                    : null,
                'other_deliveries'  => StockDeliveryDocumentChecks::otherDeliveriesMentioned($stockDelivery, $haystack),
            ],
        ];
    }

    /**
     * A line keeps its type only when both readers gave it the same one; otherwise the reviewer chooses.
     *
     * @param  array<int, array{description: ?string, type: ?string, amount: ?float}>  $lines
     * @param  array<int, array{description: ?string, type: ?string, amount: ?float}>  $otherLines
     * @return array<int, array{description: ?string, type: ?string, amount: ?float}>
     */
    private function agreedLines(array $lines, array $otherLines): array
    {
        $cents = fn (array $line) => $line['amount'] === null ? null : (int) round((float) $line['amount'] * 100);

        foreach ($lines as &$line) {
            $match = null;
            foreach ($otherLines as $index => $otherLine) {
                if ($cents($otherLine) === $cents($line)) {
                    $match = $index;
                    if ($otherLine['type'] === $line['type']) {
                        break;
                    }
                }
            }

            if ($match === null || $otherLines[$match]['type'] !== $line['type']) {
                $line['type'] = null;
            }
            if ($match !== null) {
                unset($otherLines[$match]);
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function ask(string $model, Media $media, string $content): array
    {
        $types = collect(StockDeliveryServiceInvoiceTypeEnum::billTypes())
            ->map(fn (StockDeliveryServiceInvoiceTypeEnum $type) => $type->value.' ('.self::typeHints()[$type->value].')')
            ->implode('; ');

        $prompt = 'Read this invoice for transport, customs or other services on an import shipment and return its content as JSON.'
            ."\nCopy every number exactly as printed. Leave a field null when the document does not show it: never guess, round, convert or calculate a missing number."
            ."\nlines are the services billed, one per printed line, with the amount of the line before VAT. Give each line the type that fits it best: $types."
            ."\nnet_total is the total before VAT, vat_total the VAT, total_payable the amount to pay. Dates as YYYY-MM-DD, currency as the ISO 4217 code printed on the document."
            ."\nreferences are every order, shipment, booking, container, bill of lading and customs declaration number printed on it, copied exactly."
            ."\nissuer is the company that issued the invoice, invoice_number its number."
            ."\nSet is_invoice to false when the document is not an invoice for services, for instance an invoice for goods, a packing list or a customs declaration.";

        $response = $this->aiRequest()
            ->timeout(240)
            ->post('chat/completions', [
                'model'           => $this->aiModel($model),
                'messages'        => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt], $this->documentPart($media, $content)]]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'service_invoice', 'strict' => true, 'schema' => self::schema()]],
            ]);

        $this->guardAICreditResponse($response, 'grp');

        $response->throw();

        $reading = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($reading) || array_diff(self::KEYS, array_keys($reading)) || !is_array($reading['lines']) || !is_array($reading['references'])) {
            throw new RuntimeException("$model did not return a service invoice reading");
        }

        $reading['lines'] = array_map(fn ($line) => [
            'description' => $line['description'] ?? null,
            'type'        => StockDeliveryServiceInvoiceTypeEnum::tryFrom((string) ($line['type'] ?? ''))?->value,
            'amount'      => isset($line['amount']) && is_numeric($line['amount']) ? (float) $line['amount'] : null,
        ], $reading['lines']);
        $reading['references'] = array_values(array_filter(array_map('strval', $reading['references'])));
        $reading['currency']   = StockDeliveryDocumentChecks::currencyCode($reading['currency'] ?? null);

        return array_intersect_key($reading, array_flip(self::KEYS));
    }

    /**
     * @return array<string, string>
     */
    private static function typeHints(): array
    {
        return [
            'freight'           => 'sea, air, rail or road freight',
            'local_transport'   => 'delivery from the port or terminal to the warehouse',
            'duty'              => 'customs duty',
            'anti_dumping'      => 'anti-dumping duty',
            'import_levy'       => 'other import taxes such as plastic tax or excise',
            'tax'               => 'import VAT charged as a line',
            'customs_clearance' => 'the broker\'s fee for the import or export declaration',
            'port_charges'      => 'port, terminal and handling charges',
            'storage'           => 'storage, demurrage, detention and waiting time',
            'inspection'        => 'inspections, scans, checks and certificates',
            'documentation'     => 'document, bill of lading and administration fees',
            'insurance'         => 'insurance',
            'fine'              => 'fines and penalties',
            'other'             => 'anything else',
        ];
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
                'net_total'      => $nullableNumber,
                'vat_total'      => $nullableNumber,
                'total_payable'  => $nullableNumber,
                'lines'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['description', 'type', 'amount'],
                        'properties'           => [
                            'description' => $nullableString,
                            'type'        => ['type' => 'string', 'enum' => array_column(StockDeliveryServiceInvoiceTypeEnum::billTypes(), 'value')],
                            'amount'      => $nullableNumber,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'attachments'   => ['sometimes', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'max:50000', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv'],
        ];
    }

    public function asController(StockDelivery $stockDelivery, Media $media, ActionRequest $request): void
    {
        abort_if($stockDelivery->organisation->type === OrganisationTypeEnum::AGENT, 403);
        $this->initialisation($stockDelivery->organisation, $request);

        abort_unless(ReadStockDeliveryInvoice::pivot($stockDelivery, $media)->where('scope', PurchaseOrderAttachmentScopeEnum::SERVICE_INVOICE->value)->exists(), 404);
        abort_if(self::isApplied($stockDelivery, $media), 422);

        self::queue($stockDelivery, $media);
    }

    public function inUpload(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        abort_if($stockDelivery->organisation->type === OrganisationTypeEnum::AGENT, 403);
        $this->initialisation($stockDelivery->organisation, $request);

        foreach ($this->validatedData['attachments'] ?? [] as $file) {
            $media = SaveModelAttachment::make()->action($stockDelivery, [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'scope'        => PurchaseOrderAttachmentScopeEnum::SERVICE_INVOICE->value,
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);

            if (!self::isApplied($stockDelivery, $media)) {
                self::queue($stockDelivery, $media);
            }
        }
    }

    public static function queue(StockDelivery $stockDelivery, Media $media): void
    {
        ReadStockDeliveryInvoice::storeReading($stockDelivery, $media, ['state' => 'reading', 'kind' => self::KIND]);

        self::dispatch($stockDelivery, $media);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
