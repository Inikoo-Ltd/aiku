<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Media;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Every document of a shipment's import file in one download for the accountants (HELP-4795), in checklist order:
 * a ZIP with all of them, or one PDF to print with a cover page listing them. The PDF merges PDFs and photos with
 * Ghostscript; spreadsheets and other files cannot be printed into it, so the cover names them and points to the ZIP,
 * and a merge Ghostscript refuses (a damaged or password-protected PDF) falls back to the ZIP.
 */
class DownloadStockDeliveryImportFileDocuments extends OrgAction
{
    private const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    public function handle(StockDelivery $stockDelivery, string $format): BinaryFileResponse
    {
        $documents = $this->documents($stockDelivery);
        abort_if($documents->isEmpty(), 404);

        if ($format === 'pdf') {
            $pdf = $this->pdf($stockDelivery, $documents);
            if ($pdf) {
                return response()->download($pdf, $this->fileName($stockDelivery, 'pdf'))->deleteFileAfterSend();
            }
        }

        return response()->download($this->zip($documents), $this->fileName($stockDelivery, 'zip'))->deleteFileAfterSend();
    }

    /**
     * @return Collection<int, array{label: string, name: string, media: Media}>
     */
    public function documents(StockDelivery $stockDelivery): Collection
    {
        $stepLabels = GetStockDeliveryImportFile::labels();
        $scopeLabels = PurchaseOrderAttachmentScopeEnum::labels();
        $stepOfScope = [];
        foreach (GetStockDeliveryImportFile::STEPS as $step) {
            $scope = GetStockDeliveryImportFile::scope($step);
            if ($scope) {
                $stepOfScope[$scope->value] = $stepLabels[$step];
            }
        }
        $order = array_flip(array_keys($stepOfScope));

        $files = IndexStockDeliveryAttachmentsJson::make()->handle($stockDelivery)
            ->map(fn (array $file) => ['media_id' => $file['media_id'], 'scope' => $file['scope'], 'caption' => $file['caption']]);

        $depositProofs = PurchaseOrderDepositPayment::whereIn('purchase_order_id', $stockDelivery->purchaseOrders()->pluck('purchase_orders.id'))
            ->whereNotNull('media_id')
            ->orderBy('date')
            ->pluck('media_id')
            ->map(fn (int $mediaId) => ['media_id' => $mediaId, 'scope' => PurchaseOrderAttachmentScopeEnum::DEPOSIT_PAYMENT->value, 'caption' => null]);

        $rows  = $files->concat($depositProofs)->unique('media_id');
        $media = Media::whereIn('id', $rows->pluck('media_id'))->get()->keyBy('id');

        return $rows
            ->sortBy(fn (array $row) => $order[$row['scope']] ?? PHP_INT_MAX)
            ->map(fn (array $row) => ($file = $media->get($row['media_id'])) && is_file($file->getPath()) ? [
                'label' => $stepOfScope[$row['scope']] ?? $scopeLabels[$row['scope']] ?? (string) $row['scope'],
                'name'  => $this->name($row['caption'] ?: pathinfo((string) $file->name, PATHINFO_FILENAME), $file->file_name),
                'media' => $file,
            ] : null)
            ->filter()
            ->values();
    }

    private function name(string $caption, ?string $fileName): string
    {
        $extension = pathinfo((string) $fileName, PATHINFO_EXTENSION);

        return str_replace(['/', '\\'], '-', $caption ?: 'document').($extension ? '.'.$extension : '');
    }

    private function zip(Collection $documents): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'import-file');
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($documents->values() as $index => $document) {
            $zip->addFile($document['media']->getPath(), sprintf('%02d %s - %s', $index + 1, Str::slug($document['label']), $document['name']));
        }
        $zip->close();

        return $zipPath;
    }

    private function pdf(StockDelivery $stockDelivery, Collection $documents): ?string
    {
        $workDir = sys_get_temp_dir().'/import-file-'.Str::random(12);
        File::makeDirectory($workDir);

        try {
            $parts = [];
            $leftOut = [];
            foreach ($documents->values() as $index => $document) {
                $mime = $document['media']->mime_type;
                if ($mime === 'application/pdf') {
                    $parts[] = $document['media']->getPath();
                } elseif (in_array($mime, self::IMAGE_TYPES, true)) {
                    $parts[] = $this->imagePage($document['media']->getPath(), "$workDir/image-$index.pdf");
                } else {
                    $leftOut[] = $index;
                }
            }

            $cover = "$workDir/cover.pdf";
            $coverPdf = $this->mpdf();
            $coverPdf->WriteHTML($this->coverHtml($stockDelivery, $documents->values(), $leftOut));
            $coverPdf->Output($cover, 'F');

            $output = tempnam(sys_get_temp_dir(), 'import-file-pdf');
            $result = Process::timeout(300)->run(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-sOutputFile='.$output, $cover, ...$parts]);

            if (!$result->successful() || !filesize($output)) {
                @unlink($output);

                return null;
            }

            return $output;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function coverHtml(StockDelivery $stockDelivery, Collection $documents, array $leftOut): string
    {
        $rows = $documents->map(fn (array $document, int $index) => '<tr><td>'.($index + 1).'</td><td>'.e($document['label']).'</td><td>'.e($document['name'])
            .(in_array($index, $leftOut, true) ? ' <i style="color:#b45309">('.e(__('not printable, in the ZIP download')).')</i>' : '').'</td></tr>')->implode('');

        return '<h2 style="font-size:16pt">'.e(__('Import file documents')).': '.e($stockDelivery->reference).'</h2>'
            .'<p style="color:#555">'.e($stockDelivery->parent_name).' · '.e(now()->toDateString()).'</p>'
            .'<table style="width:100%;border-collapse:collapse;font-size:10pt" cellpadding="4"><thead><tr style="background:#eee"><th align="left">#</th><th align="left">'
            .e(__('Step')).'</th><th align="left">'.e(__('File')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function mpdf(): Mpdf
    {
        return new Mpdf(['tempDir' => sys_get_temp_dir().'/mpdf', 'margin_top' => 15, 'margin_bottom' => 15]);
    }

    private function imagePage(string $imagePath, string $pdfPath): string
    {
        $mpdf = $this->mpdf();
        $mpdf->WriteHTML('<div style="text-align:center"><img src="'.e($imagePath).'" style="max-width:100%;max-height:260mm" /></div>');
        $mpdf->Output($pdfPath, 'F');

        return $pdfPath;
    }

    private function fileName(StockDelivery $stockDelivery, string $extension): string
    {
        return Str::slug($stockDelivery->reference.' documents').'.'.$extension;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "procurement.{$this->organisation->id}.view",
            "accounting.{$this->organisation->id}.view",
        ]);
    }

    public function rules(): array
    {
        return [
            'format' => ['sometimes', Rule::in(['zip', 'pdf'])],
        ];
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData['format'] ?? 'zip');
    }
}
