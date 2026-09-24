<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DownloadArtefactLabelPdf extends OrgAction
{
    use WithArtefactLabelAuthorisation;

    private const PDF_MIME_TYPE = 'application/pdf';

    /**
     * @param  array<string, string>  $runTexts  keyed by field source, replacing what the design holds
     *
     * @throws \Mpdf\MpdfException
     */
    public function handle(Artefact|OrgStock $model, ArtefactLabel $artefactLabel, array $runTexts = []): Response
    {
        abort_unless($artefactLabel->state === ArtefactLabelStateEnum::PUBLISHED, 404);

        $artwork = $artefactLabel->artwork;

        try {
            return PdfArtefactLabelSheet::make()->handle(
                $model,
                $this->applyRunTexts($artefactLabel->layout, $runTexts),
                $artwork ? ['path' => $artwork->getPath(), 'mime_type' => $artwork->mime_type] : null
            );
        } catch (HttpException $exception) {
            $hasOriginalPdf = $artwork && $artwork->mime_type === self::PDF_MIME_TYPE && is_file($artwork->getPath());

            if (!$hasOriginalPdf) {
                throw $exception;
            }

            return response()->file($artwork->getPath(), ['Content-Type' => self::PDF_MIME_TYPE])
                ->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, Str::slug($artefactLabel->name).'.pdf');
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if (!isset($this->production)) {
            return $this->canViewLabels($request);
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
            "productions_rd.{$this->production->id}.view",
        ]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Artefact $artefact, ArtefactLabel $label, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($artefact->production, $request);

        return $this->handle($artefact, $label, $this->getRunTexts($request));
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function inOrgStock(OrgStock $orgStock, ArtefactLabel $label, ActionRequest $request): Response
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock, $label, $this->getRunTexts($request));
    }

    /**
     * A run carries its own batch code and expiry date, and the board prints from the run, not from
     * the design. Anything not sent keeps what the label was designed with.
     *
     * @return array<string, string>
     */
    public function getRunTexts(ActionRequest $request): array
    {
        return array_filter([
            'batch_code'  => trim((string) $request->query('batch_code')),
            'expiry_date' => $this->getRunExpiryDate($request->query('expiry_date')),
        ], fn (string $text) => $text !== '');
    }

    /**
     * Only a date reaches the sheet. Anything else, a timestamp that slipped through a caller or a
     * hand edited link, is dropped rather than printed, because a label is read by people who
     * cannot tell a formatting accident from a real date.
     */
    private function getRunExpiryDate(mixed $expiryDate): string
    {
        $expiryDate = trim((string) $expiryDate);

        if ($expiryDate === '') {
            return '';
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $expiryDate);
            } catch (Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $expiryDate) {
                return $date->format('d/m/Y');
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  array<string, string>  $runTexts
     * @return array<string, mixed>
     */
    private function applyRunTexts(array $layout, array $runTexts): array
    {
        if (!$runTexts) {
            return $layout;
        }

        foreach (Arr::get($layout, 'fields', []) ?? [] as $index => $field) {
            $source = Arr::get($field, 'source');

            if (isset($runTexts[$source])) {
                $layout['fields'][$index]['text'] = $runTexts[$source];
            }
        }

        return $layout;
    }
}
