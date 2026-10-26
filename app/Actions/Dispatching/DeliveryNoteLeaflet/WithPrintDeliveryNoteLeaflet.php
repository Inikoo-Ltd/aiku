<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\Fulfilment\PalletReturnLeaflet;
use Exception;
use Illuminate\Support\Arr;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mccarlosen\LaravelMpdf\LaravelMpdf;
use Rawilk\Printing\Api\PrintNode\Enums\PrintJobOption;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;

trait WithPrintDeliveryNoteLeaflet
{
    protected function leafletPrinterId(User $user): ?int
    {
        $printerId = Arr::get($user->settings, 'preferred_leaflet_printer_id') ?: Arr::get($user->settings, 'preferred_printer_id');

        return $printerId ? (int) $printerId : null;
    }

    /**
     * Sends the leaflet's media file to the given PrintNode printer and marks it printed.
     * Uses WithPrintNode::printPdf() (the consuming action must also use WithPrintNode).
     */
    protected function printDeliveryNoteLeaflet(DeliveryNoteLeaflet|PalletReturnLeaflet $deliveryNoteLeaflet, int $printerId): ?PrintJob
    {
        $pdf = $this->leafletPdf($deliveryNoteLeaflet);

        if ($pdf === null) {
            return null;
        }

        $result = $this->printPdf(
            title: $deliveryNoteLeaflet->name,
            printId: $printerId,
            pdfBase64: base64_encode($pdf),
            options: $this->leafletPaperOption($printerId) + [PrintJobOption::FitToPage->value => false]
        );

        $this->markLeafletPrinted($deliveryNoteLeaflet);

        return $result;
    }

    /**
     * The leaflet as the PDF that goes on the A6 card: the uploaded PDF as it is, an uploaded image
     * laid on an A6 page, or the personalised message written on an A6 page.
     */
    protected function leafletPdf(DeliveryNoteLeaflet|PalletReturnLeaflet $deliveryNoteLeaflet): ?string
    {
        $media = $deliveryNoteLeaflet->media;

        if (!$media) {
            return filled($deliveryNoteLeaflet->message)
                ? PDF::loadHTML($this->personalisedMessageHtml($deliveryNoteLeaflet->message), $this->personalisedMessagePdfConfig())->output()
                : null;
        }

        $bytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        return $media->mime_type && str_starts_with($media->mime_type, 'image/') ? $this->imageLeafletPdf($bytes) : $bytes;
    }

    protected function markLeafletPrinted(DeliveryNoteLeaflet|PalletReturnLeaflet $deliveryNoteLeaflet): void
    {
        $deliveryNoteLeaflet->update([
            'state'              => DeliveryNoteLeafletStateEnum::PRINTED,
            'printed_at'         => now(),
            'printed_by_user_id' => request()->user()?->id,
        ]);
    }

    /**
     * Names the printer's own A6 paper for the job. Without it the printer uses its default paper,
     * usually A4, and lays the A6 page in the middle of that sheet, half off the card.
     * A driver that offers no A6 gets its nearest paper (Postcard, 100 x 148 mm) and the page fitted to it.
     *
     * @return array<string, string|bool>
     */
    protected function leafletPaperOption(int $printerId): array
    {
        $this->ensureClientInitialized();

        try {
            $papers = Printer::retrieve($printerId)->capabilities?->papers;
        } catch (Exception) {
            return [];
        }

        $papers = json_decode(json_encode($papers ?? []), true) ?: [];
        $paper  = $this->a6PaperName($papers);

        if ($paper !== null) {
            return [PrintJobOption::Paper->value => $paper];
        }

        $paper = $this->nearA6PaperName($papers);

        return $paper === null ? [] : [PrintJobOption::Paper->value => $paper, PrintJobOption::FitToPage->value => true];
    }

    /**
     * @param  array<string, array{0: int|null, 1: int|null}>  $papers  paper name => [width, height] in tenths of a millimetre
     */
    protected function nearA6PaperName(array $papers): ?string
    {
        $distances = [];
        foreach ($papers as $name => $size) {
            $widthGap  = abs((int) Arr::get($size, 0) - 1050);
            $heightGap = abs((int) Arr::get($size, 1) - 1480);
            if (max($widthGap, $heightGap) <= 60) {
                $distances[(string) $name] = $widthGap + $heightGap;
            }
        }
        asort($distances);

        return array_key_first($distances);
    }

    /**
     * @param  array<string, array{0: int|null, 1: int|null}>  $papers  paper name => [width, height] in tenths of a millimetre
     */
    protected function a6PaperName(array $papers): ?string
    {
        foreach ($papers as $name => $size) {
            if (abs((int) Arr::get($size, 0) - 1050) <= 20 && abs((int) Arr::get($size, 1) - 1480) <= 20) {
                return (string) $name;
            }
        }

        return Arr::first(array_map('strval', array_keys($papers)), fn (string $name) => preg_match('/\bA6\b/i', $name) === 1);
    }

    /**
     * Lays an image on one A6 page, scaled to fit whole and centred, never cropped.
     * The bytes go in as an mPDF image variable: inlined as base64 they exceed the HTML size mPDF accepts.
     */
    protected function imageLeafletPdf(string $bytes): string
    {
        $size = getimagesizefromstring($bytes);
        if ($size === false) {
            throw ValidationException::withMessages([
                'messages' => __('The insert image could not be read. Ask the customer to upload it again as an A6 PDF.'),
            ]);
        }

        [$width, $height]        = $size;
        [$pageWidth, $pageHeight] = $width > $height ? [148, 105] : [105, 148];

        $pdf = new LaravelMpdf([
            'format'        => [$pageWidth, $pageHeight],
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $scale       = min($pageWidth / $width, $pageHeight / $height);
        $imageWidth  = $width * $scale;
        $imageHeight = $height * $scale;

        $mpdf                       = $pdf->getMpdf();
        $mpdf->imageVars['leaflet'] = $bytes;
        $mpdf->AddPage();
        $mpdf->Image('var:leaflet', ($pageWidth - $imageWidth) / 2, ($pageHeight - $imageHeight) / 2, $imageWidth, $imageHeight, '', '', true, false);

        return $pdf->output();
    }

    /**
     * The default mPDF fonts have no emoji, so customers' emoji printed as blanks (HELP-4298).
     * Noto Emoji is monochrome: mPDF cannot draw colour emoji fonts. mPDF misreads the font's
     * ligature table, so flags and joined emoji are given private characters instead, see
     * resources/fonts/NotoEmoji/make-font.php.
     *
     * @return array<string, mixed>
     */
    protected function personalisedMessagePdfConfig(): array
    {
        $defaults = (new ConfigVariables())->getDefaults();
        $fonts    = (new FontVariables())->getDefaults();

        return [
            'format'        => 'A6',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 12,
            'margin_bottom' => 12,
            'fontDir'       => [...$defaults['fontDir'], resource_path('fonts/NotoEmoji')],
            'fontdata'      => $fonts['fontdata'] + ['notoemoji' => ['R' => 'NotoEmoji-Regular.ttf']],
        ];
    }

    protected function personalisedMessageHtml(string $message): string
    {
        $sequences = require resource_path('fonts/NotoEmoji/sequences.php');

        $html = preg_replace_callback(
            '/[0-9#*]\x{FE0F}?\x{20E3}|[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}][\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}\x{1F3FB}-\x{1F3FF}\x{200D}\x{FE0F}\x{20E3}]*/u',
            fn (array $emoji) => '<span style="font-family: notoemoji;">'.strtr(str_replace("\u{FE0F}", '', $emoji[0]), $sequences).'</span>',
            e($message)
        );

        return '<div style="font-family: sans-serif; font-size: 14pt; line-height: 1.6;">'.nl2br($html).'</div>';
    }
}
