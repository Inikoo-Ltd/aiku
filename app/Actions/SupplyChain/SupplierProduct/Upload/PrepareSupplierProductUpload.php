<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Models\Helpers\Upload;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads and checks an uploaded supplier product sheet and keeps every row as a preview record.
 * Nothing is created here; ImportSupplierProductUpload does that once the preview is confirmed.
 */
class PrepareSupplierProductUpload
{
    use AsAction;

    public function handle(Supplier $supplier, Upload $upload): Upload
    {
        $upload->update(['state' => UploadStateEnum::CHECKING]);
        $upload->records()->delete();

        $sheet = ReadSupplierProductSheet::run($this->localPath($upload));

        if ($sheet['errors'] !== []) {
            $upload->update([
                'state'        => UploadStateEnum::REFUSED,
                'number_rows'  => 0,
                'number_fails' => 0,
                'data'         => ['errors' => $sheet['errors']],
            ]);

            return $upload;
        }

        $checker = CheckSupplierProductSheet::make();
        $rows    = $checker->handle($supplier, $sheet);

        foreach ($rows as $row) {
            $upload->records()->create([
                'row_number' => $row['row'],
                'values'     => $row['values'],
                'errors'     => collect($row['findings'])->where('level', 'error')->pluck('message')->values()->all(),
                'status'     => UploadRecordStatusEnum::PREVIEW,
                'data'       => ['findings' => $row['findings'], 'decisions' => []],
            ]);
        }

        $upload->update([
            'state'          => UploadStateEnum::WAITING_CONFIRMATION,
            'number_rows'    => count($rows),
            'number_success' => 0,
            'number_fails'   => 0,
            'data'           => [
                'heading_row'   => $sheet['heading_row'],
                'order_columns' => array_values($sheet['order_columns']),
                'new_draft'     => [],
                'ai'            => 'queued',
                'declaration'   => $sheet['declaration'],
                'packaging'     => [
                    'rows'    => count($sheet['packaging']),
                    'orphans' => $checker->orphanPackagingRows($sheet, $rows),
                    'unread'  => $sheet['packaging_unread'],
                ],
            ],
        ]);

        CheckSupplierProductUploadWithAI::dispatch($upload);

        return $upload;
    }

    protected function localPath(Upload $upload): string
    {
        return Storage::disk('excel-uploads')->path($upload->path.'/'.$upload->filename);
    }
}
