<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Models\Helpers\Upload;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Runs Jev's per row questions and then the final review on a supplier product upload preview, in the
 * background; Import waits until both have finished.
 */
class CheckSupplierProductUploadWithAI
{
    use AsAction;

    public int $jobTimeout = 900;

    public function handle(Upload $upload): Upload
    {
        $upload->update(['data' => array_merge($upload->data, ['ai' => 'running'])]);

        CheckSupplierProductUploadWithJev::run($upload);
        ReviewSupplierProductUpload::run($upload->refresh());

        $upload->refresh();
        $upload->update(['data' => array_merge($upload->data, ['ai' => 'done'])]);

        return $upload;
    }
}
