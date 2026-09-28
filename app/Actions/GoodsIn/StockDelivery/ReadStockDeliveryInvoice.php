<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Throwable;

/**
 * Reads a supplier invoice attached to a delivery into its lines, charges and total. The reading is
 * kept on the attachment and only ever proposed: nothing is costed until someone reviews and applies it.
 */
class ReadStockDeliveryInvoice extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithAICreditErrorHandler;

    public int $jobTimeout = 300;

    private const string MODEL = 'gpt-5-mini';

    public function handle(StockDelivery $stockDelivery, Media $media): void
    {
        try {
            $reading = $this->read($stockDelivery, $media);
            self::storeReading($stockDelivery, $media, ['state' => 'read', 'read_at' => now()->toIso8601String(), ...$reading]);
        } catch (Throwable $exception) {
            report($exception);
            self::storeReading($stockDelivery, $media, [
                'state' => 'failed',
                'error' => $this->isAICreditThrowable($exception)
                    ? __('The AI service has run out of credit.')
                    : __('The invoice could not be read.'),
            ]);
        }
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
     * @return array{is_invoice: bool, invoice_number: ?string, invoice_date: ?string, currency: ?string, lines: array<int, array{code: ?string, description: ?string, quantity: ?float, unit_price: ?float, amount: ?float}>, charges: array<int, array{label: string, amount: float}>, total: ?float}
     */
    private function read(StockDelivery $stockDelivery, Media $media): array
    {
        $expected = $stockDelivery->items()
            ->with('supplierProduct:id,code,name')
            ->get()
            ->map(fn (StockDeliveryItem $item) => $item->supplierProduct?->code.' — '.$item->supplierProduct?->name)
            ->filter()
            ->implode("\n");

        $prompt = 'Read this supplier invoice and return its content as JSON.'
            ."\nCopy product codes exactly as printed; leave a field null when the document does not show it, never guess or calculate a missing number."
            ."\nlines are the goods; charges are anything else billed (freight, packing, bank fees, samples, discounts as negative amounts)."
            ."\nAmounts are line totals before tax; total is the amount payable. Dates as YYYY-MM-DD, currency as the ISO 4217 code."
            ."\nSet is_invoice to false when the document is not an invoice or proforma."
            ."\nFor reference, the products we expect on it (our supplier code — name):\n".$expected;

        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(240)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => self::MODEL,
                'messages'        => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $prompt], $this->documentPart($media)]]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'supplier_invoice', 'strict' => true, 'schema' => self::schema()]],
            ]);

        $this->guardAICreditResponse($response, 'grp');

        $response->throw();

        return json_decode((string) $response->json('choices.0.message.content'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPart(Media $media): array
    {
        $content  = stream_get_contents($media->stream());
        $mimeType = (string) $media->mime_type;

        if ($mimeType === 'application/pdf') {
            return ['type' => 'file', 'file' => ['filename' => $media->file_name, 'file_data' => 'data:application/pdf;base64,'.base64_encode($content)]];
        }

        if (Str::startsWith($mimeType, 'image/')) {
            return ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,".base64_encode($content)]];
        }

        return ['type' => 'text', 'text' => "The invoice, as CSV:\n".$this->spreadsheetAsCsv($content, $media->file_name)];
    }

    private function spreadsheetAsCsv(string $content, string $fileName): string
    {
        $base = tempnam(sys_get_temp_dir(), 'invoice-');
        $path = $base.'.'.pathinfo($fileName, PATHINFO_EXTENSION);

        try {
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $csv         = '';

            foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
                ob_start();
                (new Csv($spreadsheet))->setSheetIndex($index)->save('php://output');
                $csv .= '# '.$sheet->getTitle()."\n".ob_get_clean()."\n";
            }

            return $csv;
        } finally {
            @unlink($path);
            @unlink($base);
        }
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
            'required'             => ['is_invoice', 'invoice_number', 'invoice_date', 'currency', 'lines', 'charges', 'total'],
            'properties'           => [
                'is_invoice'     => ['type' => 'boolean'],
                'invoice_number' => $nullableString,
                'invoice_date'   => $nullableString,
                'currency'       => $nullableString,
                'total'          => $nullableNumber,
                'lines'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['code', 'description', 'quantity', 'unit_price', 'amount'],
                        'properties'           => [
                            'code'        => $nullableString,
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

        self::storeReading($stockDelivery, $media, ['state' => 'reading']);

        self::dispatch($stockDelivery, $media);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
