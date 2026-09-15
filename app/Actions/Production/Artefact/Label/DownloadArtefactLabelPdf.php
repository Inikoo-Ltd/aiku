<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactLabelStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactLabel;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadArtefactLabelPdf extends OrgAction
{
    private const PDF_MIME_TYPE = 'application/pdf';

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Artefact $artefact, ArtefactLabel $artefactLabel): Response
    {
        abort_unless($artefactLabel->state === ArtefactLabelStateEnum::PUBLISHED, 404);

        $artwork = $artefactLabel->artwork;

        try {
            return PdfArtefactLabelSheet::make()->handle(
                $artefact,
                $artefactLabel->layout,
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

        return $this->handle($artefact, $label);
    }
}
