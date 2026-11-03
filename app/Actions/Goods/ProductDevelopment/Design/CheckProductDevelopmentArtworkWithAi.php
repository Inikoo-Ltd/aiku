<?php

/*
 * Author Louis Perez
 * Created on 03-11-2026-14h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentArtworkCheck;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use LengthException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * The AI pre-check of an artwork: a model reads the PDF against the SKU's approved label content (pack text,
 * pictograms, marks) and lists what is missing, different or misspelt. The barcode numbers are compared by code,
 * not by the model. It only advises the reviewer: it never approves, blocks or changes the artwork.
 */
class CheckProductDevelopmentArtworkWithAi extends OrgAction
{
    use WithProductDevelopmentAuthorisation;
    use WithAICreditErrorHandler;
    use WithAIGateway;

    public int $jobTimeout = 600;

    public const array ITEM_STATUSES = ['matches', 'differs', 'missing', 'unclear'];

    public const array ISSUE_KINDS = ['spelling', 'inconsistency', 'legibility', 'other'];

    public const int SLIM_ABOVE_BYTES = 4_000_000;

    public const int MAX_SENT_BYTES = 4_500_000;

    private ProductDevelopmentArtwork $artwork;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->artwork->productDevelopment;
        $access             = $this->productDevelopmentAccess();

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && ($access->canManageArtwork($request->user(), $productDevelopment) || $access->canApproveDesign($request->user(), $productDevelopment));
    }

    public function handle(ProductDevelopmentArtworkCheck $check): ProductDevelopmentArtworkCheck
    {
        $model = (string) config('services.product_development_artwork_check.model');

        if ($check->artwork->version !== $check->version) {
            $check->update(['state' => ProductDevelopmentArtworkCheck::FAILED, 'error' => __('A newer version was uploaded before this one was checked.'), 'checked_at' => now()]);

            return $check;
        }

        try {
            $check->update([
                'state'      => ProductDevelopmentArtworkCheck::DONE,
                'result'     => $this->check($check->artwork, $model),
                'error'      => null,
                'model'      => $model,
                'checked_at' => now(),
            ]);
        } catch (LengthException $exception) {
            $check->update(['state' => ProductDevelopmentArtworkCheck::FAILED, 'error' => $exception->getMessage(), 'model' => $model, 'checked_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            $check->update([
                'state'      => ProductDevelopmentArtworkCheck::FAILED,
                'error'      => $this->isAICreditThrowable($exception) ? __('The AI service has run out of credit.') : __('The artwork could not be checked.'),
                'model'      => $model,
                'checked_at' => now(),
            ]);
        }

        return $check;
    }

    /**
     * Starts a check of the artwork's current version, replacing an earlier check of that version. A check
     * still running is returned as it is, so a second click does not pay for a second reading.
     */
    public static function queue(ProductDevelopmentArtwork $artwork, User $user): ProductDevelopmentArtworkCheck
    {
        $running = $artwork->checks()->where('version', $artwork->version)->first();
        if ($running?->isRunning()) {
            return $running;
        }

        $check = ProductDevelopmentArtworkCheck::updateOrCreate(
            ['artwork_id' => $artwork->id, 'version' => $artwork->version],
            ['state' => ProductDevelopmentArtworkCheck::CHECKING, 'result' => null, 'error' => null, 'requested_by_id' => $user->id, 'checked_at' => null]
        );

        self::dispatch($check);

        return $check->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function check(ProductDevelopmentArtwork $artwork, string $model): array
    {
        $pdf = DownloadProductDevelopmentArtwork::bytes($artwork);
        if ($pdf === null) {
            throw new RuntimeException('The artwork has no PDF to check');
        }

        $content  = GetProductDevelopmentLabelContent::run($artwork->productDevelopment, $artwork->item->load('tradeUnit'));
        $expected = self::expected($content);
        $sent     = self::slim($pdf);
        if (strlen($sent) > self::MAX_SENT_BYTES) {
            throw new LengthException(__('The artwork is too large for the AI check (:size MB), even with its images reduced.', ['size' => round(strlen($sent) / 1_000_000, 1)]));
        }
        $reading  = $this->ask($artwork, $model, $sent, $expected);
        $text     = self::pdfText($pdf);
        $statuses = collect($reading['items'])->keyBy('key');
        $complete = (bool) $artwork->section->needs_compliance_text;

        return [
            'carries_label_text' => $complete,
            'summary'            => $reading['summary'],
            'items'              => array_map(function (array $item) use ($statuses, $complete) {
                $status = Arr::get($statuses->get($item['key']), 'status', 'unclear');

                return [
                    ...Arr::only($item, ['key', 'group', 'label', 'expected']),
                    'status' => !$complete && $status === 'missing' ? 'not_on_artwork' : $status,
                    'note'   => Arr::get($statuses->get($item['key']), 'note'),
                ];
            }, $expected),
            'issues'             => $reading['issues'],
            'barcodes'           => self::barcodes($content['barcodes'], $text, $reading['barcode_numbers']),
            'text_available'     => $text !== '',
        ];
    }

    /**
     * What the artwork should carry, from the SKU's approved label content.
     *
     * @param array<string, mixed> $content
     *
     * @return array<int, array{key: string, group: string, label: string, expected: string|null}>
     */
    public static function expected(array $content): array
    {
        return [
            ...array_map(fn (array $line) => ['key' => 'text.'.$line['key'], 'group' => 'text', 'label' => $line['label'], 'expected' => $line['value']], $content['text']),
            ...array_map(fn (array $pictogram, int $index) => ['key' => 'pictogram.'.$index, 'group' => 'pictogram', 'label' => $pictogram['label'], 'expected' => null], $content['pictograms'], array_keys($content['pictograms'])),
            ...array_map(fn (array $mark) => ['key' => 'mark.'.$mark['key'], 'group' => 'mark', 'label' => $mark['label'], 'expected' => null], $content['marks']),
        ];
    }

    /**
     * Each approved barcode is found when its number is printed on the artwork: read from the PDF's own text when
     * it has any, otherwise from the numbers the model read. The bars themselves are not scanned.
     *
     * @param array<int, array<string, mixed>> $barcodes
     * @param array<int, string>               $numbersRead
     *
     * @return array<int, array<string, mixed>>
     */
    public static function barcodes(array $barcodes, string $text, array $numbersRead): array
    {
        $digits   = fn (?string $value) => preg_replace('/\D/', '', (string) $value);
        preg_match_all('/\d[\d \-]*\d/', $text, $printed);
        $inText   = array_map($digits, $printed[0]);
        $read     = array_values(array_unique(array_filter(array_map($digits, $numbersRead))));
        $approved = [];
        $rows     = [];

        foreach ($barcodes as $barcode) {
            $value = $barcode['value'] ? $digits($barcode['value']) : '';
            if ($value === '') {
                continue;
            }
            $approved[] = $value;

            $rows[] = [
                'level'  => $barcode['level'],
                'label'  => $barcode['label'],
                'value'  => $barcode['value'],
                'status' => match (true) {
                    in_array($value, $inText, true)                  => 'found',
                    in_array($value, $read, true)                    => 'read_by_ai',
                    default                                          => 'not_found',
                },
            ];
        }

        foreach (array_diff($read, $approved) as $unknown) {
            if (strlen($unknown) >= 8) {
                $rows[] = ['level' => null, 'label' => __('Unknown number'), 'value' => $unknown, 'status' => 'unknown'];
            }
        }

        return $rows;
    }

    /**
     * Print artwork PDFs carry full resolution images (19 MB is common), which the model does not need and
     * OpenRouter refuses (5 MB passes, 8 MB is refused). Ghostscript brings the images down to 150 dpi and leaves
     * the text, vectors and colours as they are. A PDF that is already small is sent untouched.
     */
    public static function slim(string $pdf): string
    {
        if (strlen($pdf) <= self::SLIM_ABOVE_BYTES) {
            return $pdf;
        }

        $source = tempnam(sys_get_temp_dir(), 'artwork-');
        $target = $source.'.slim.pdf';

        try {
            file_put_contents($source, $pdf);
            $result = Process::timeout(120)->run(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-dPDFSETTINGS=/ebook', '-sColorConversionStrategy=LeaveColorUnchanged', '-o', $target, $source]);
            $slim   = $result->successful() && is_file($target) ? (string) file_get_contents($target) : '';

            return $slim !== '' && strlen($slim) < strlen($pdf) ? $slim : $pdf;
        } catch (ProcessTimedOutException) {
            return $pdf;
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    private static function pdfText(string $pdf): string
    {
        try {
            return trim((new PdfParser())->parseContent($pdf)->getText());
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array<int, array<string, mixed>> $expected
     *
     * @return array{items: array<int, array{key: string, status: string, note: string|null}>, issues: array<int, array{kind: string, text: string, quote: string|null}>, barcode_numbers: array<int, string>, summary: string}
     */
    private function ask(ProductDevelopmentArtwork $artwork, string $model, string $pdf, array $expected): array
    {
        $expectedLines = implode("\n", array_map(
            fn (array $item) => '- '.$item['key'].': '.$item['label'].($item['expected'] !== null ? ' = "'.$item['expected'].'"' : ' (symbol)'),
            $expected
        ));

        $prompt = 'You are the first check of a product packaging artwork, before a person reviews it. Compare the attached artwork PDF with the approved content below.'
            ."\nThe artwork is the \"".$artwork->section->name.'" of SKU '.$artwork->item->tradeUnit->code.'.'
            .($artwork->section->needs_compliance_text
                ? "\nThis artwork must carry all the approved content."
                : "\nThis artwork does not have to carry all the approved content: report an item as missing when it is not there, but only what is printed can be wrong.")
            ."\nFor each approved item give its status: matches (printed as approved; small layout or line break differences do not matter), differs (printed but the wording, number or unit is different: say exactly what differs), missing (not printed) or unclear (cannot be read with confidence)."
            ."\nItems marked (symbol) are pictograms or marks: matches when the symbol is printed."
            ."\nThen list other issues you see in the printed text: spelling or grammar mistakes, the same thing printed two different ways, text too small or too low in contrast to read. Quote the printed text. Do not report design or style preferences."
            ."\nbarcode_numbers are the numbers printed under or beside every barcode, copied exactly digit by digit; leave it empty when none is legible. Never guess a digit."
            ."\nsummary is one short sentence for the reviewer. Never say the artwork is approved or ready: a person decides that."
            ."\nApproved content:\n".$expectedLines;

        $request = [
            'model'           => $this->aiModel($model),
            'messages'        => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'file', 'file' => ['filename' => 'artwork.pdf', 'file_data' => 'data:application/pdf;base64,'.base64_encode($pdf)]],
            ]]],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'artwork_check', 'strict' => true, 'schema' => self::schema(array_column($expected, 'key'))]],
        ];

        if ($this->usesOpenRouter()) {
            $request['provider'] = ['data_collection' => 'deny'];
        }

        $response = $this->aiRequest()->timeout(240)->post('chat/completions', $request);

        $this->guardAICreditResponse($response, 'grp');

        $response->throw();

        $reading = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($reading) || !is_array($reading['items'] ?? null) || !is_array($reading['issues'] ?? null) || !is_array($reading['barcode_numbers'] ?? null)) {
            throw new RuntimeException("$model did not return an artwork check");
        }

        return [
            'items'           => array_values(array_map(fn (array $item) => [
                'key'    => (string) ($item['key'] ?? ''),
                'status' => in_array($item['status'] ?? null, self::ITEM_STATUSES, true) ? $item['status'] : 'unclear',
                'note'   => filled($item['note'] ?? null) ? (string) $item['note'] : null,
            ], array_filter($reading['items'], 'is_array'))),
            'issues'          => array_values(array_map(fn (array $issue) => [
                'kind'  => in_array($issue['kind'] ?? null, self::ISSUE_KINDS, true) ? $issue['kind'] : 'other',
                'text'  => (string) ($issue['text'] ?? ''),
                'quote' => filled($issue['quote'] ?? null) ? (string) $issue['quote'] : null,
            ], array_filter($reading['issues'], fn ($issue) => is_array($issue) && filled($issue['text'] ?? null)))),
            'barcode_numbers' => array_values(array_map('strval', array_filter($reading['barcode_numbers'], 'is_scalar'))),
            'summary'         => (string) ($reading['summary'] ?? ''),
        ];
    }

    /**
     * @param array<int, string> $keys
     *
     * @return array<string, mixed>
     */
    private static function schema(array $keys): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['items', 'issues', 'barcode_numbers', 'summary'],
            'properties'           => [
                'items'           => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['key', 'status', 'note'],
                        'properties'           => [
                            'key'    => $keys === [] ? ['type' => 'string'] : ['type' => 'string', 'enum' => $keys],
                            'status' => ['type' => 'string', 'enum' => self::ITEM_STATUSES],
                            'note'   => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'issues'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => ['kind', 'text', 'quote'],
                        'properties'           => [
                            'kind'  => ['type' => 'string', 'enum' => self::ISSUE_KINDS],
                            'text'  => ['type' => 'string'],
                            'quote' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'barcode_numbers' => ['type' => 'array', 'items' => ['type' => 'string']],
                'summary'         => ['type' => 'string'],
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artwork->version < 1 || !ProductDevelopmentArtworks::hasFile($this->artwork)) {
            $validator->errors()->add('artwork', __('Upload or build the artwork before checking it'));
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): ProductDevelopmentArtworkCheck
    {
        $this->artwork = $productDevelopmentArtwork;
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return self::queue($productDevelopmentArtwork, $request->user());
    }

    public function action(ProductDevelopmentArtwork $artwork, User $user): ProductDevelopmentArtworkCheck
    {
        $this->asAction = true;
        $this->artwork  = $artwork;
        $this->initialisationFromGroup($artwork->productDevelopment->group, []);

        return self::queue($artwork, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonResponse(ProductDevelopmentArtworkCheck $check): array
    {
        return self::payload($check->refresh());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payload(?ProductDevelopmentArtworkCheck $check): ?array
    {
        if (!$check) {
            return null;
        }

        $abandoned = $check->state === ProductDevelopmentArtworkCheck::CHECKING && !$check->isRunning();

        return [
            'version'      => $check->version,
            'state'        => $abandoned ? ProductDevelopmentArtworkCheck::FAILED : $check->state,
            'result'       => $check->result,
            'error'        => $abandoned ? __('The check did not finish, please run it again.') : $check->error,
            'requested_by' => $check->requestedBy?->contact_name ?: $check->requestedBy?->username,
            'checked_at'   => $check->checked_at?->toIso8601String(),
        ];
    }
}
