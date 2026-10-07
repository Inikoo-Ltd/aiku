<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Runs Jev's per row questions and then the final review on a supplier product upload preview, in the
 * background; Import waits until both have finished. It runs once: a retry would pay for the review again.
 * When it fails, every row asks for "I accept responsibility" instead, so the upload is never stuck.
 */
class CheckSupplierProductUploadWithAI
{
    use AsAction;

    public int $jobTimeout = 900;

    public int $jobTries = 1;

    public function handle(Upload $upload): Upload
    {
        $this->setAiState($upload, 'running');

        try {
            CheckSupplierProductUploadWithJev::run($upload);
            ReviewSupplierProductUpload::run($upload->refresh());
        } catch (Throwable $e) {
            Log::error('CheckSupplierProductUploadWithAI: '.$e->getMessage());
            $this->markNotRun($upload);

            return $upload;
        }

        $this->setAiState($upload, 'done');

        return $upload;
    }

    public function jobFailed(Throwable $e, Upload $upload): void
    {
        $this->markNotRun($upload);
    }

    protected function markNotRun(Upload $upload): void
    {
        foreach ($upload->records()->where('status', UploadRecordStatusEnum::PREVIEW)->get() as $record) {
            /** @var UploadRecord $record */
            $data     = $record->data ?? [];
            $findings = Arr::get($data, 'findings', []);
            if (!collect($findings)->contains('code', 'ai_checks_not_run')) {
                $findings[]       = ['level' => 'block', 'code' => 'ai_checks_not_run', 'column' => null, 'message' => __('The AI checks could not run on this row.'), 'source' => 'jev'];
                $data['findings'] = $findings;
                $record->update(['data' => $data]);
            }
        }

        $this->setAiState($upload, 'failed');
    }

    protected function setAiState(Upload $upload, string $state): void
    {
        $upload->refresh();
        $upload->update(['data' => array_merge($upload->data ?? [], ['ai' => $state])]);
    }
}
