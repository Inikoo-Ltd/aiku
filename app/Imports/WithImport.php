<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 21:01:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports;

use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Events\UploadExcelProgressEvent;
use App\Events\UploadRetinaExcelProgressEvent;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;

trait WithImport
{
    public Upload $upload;
    public int $totalRows = 0;

    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->upload->records()->create(
                [
                    'values'      => $this->cleanRow(collect($failure->values()))->all(),
                    'errors'      => $failure->errors(),
                    'fail_column' => $failure->attribute(),
                    'row_number'  => $failure->row(),
                    'status'      => UploadRecordStatusEnum::FAILED
                ]
            );
            $this->updateStats();
        }
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $totalRows = $event->getReader()->getActiveSheet()->getHighestRow();
                $this->upload->update(
                    [
                        'number_rows' => $totalRows - 1
                    ]
                );
            }
        ];
    }

    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            $row          = $this->cleanRow($row);
            $uploadRecord = $this->createUploadRecord($row);
            $this->storeModel($row, $uploadRecord);
        }
    }

    public function cleanRow(Collection $row): Collection
    {
        return $row->filter(function ($value, $key) {
            return !($value === null and is_numeric($key));
        });
    }

    public function createUploadRecord(Collection $row): UploadRecord
    {
        /** @var UploadRecord $record */
        $record = $this->upload->records()->create(
            [
                'values' => $row,
                'status' => UploadRecordStatusEnum::PROCESSING
            ]
        );
        $this->updateStats();

        return $record;
    }

    public function setRecordAsCompleted(UploadRecord $record): void
    {
        $record->update(
            [
                'status' => UploadRecordStatusEnum::COMPLETE
            ]
        );
        $this->updateStats();
    }

    public function setRecordAsFailed(UploadRecord $record, $errors): void
    {
        $record->update(
            [
                'status' => UploadRecordStatusEnum::FAILED,
                'errors' => $errors
            ]
        );
        $this->updateStats();
    }

    public function updateStats(): void
    {
        $this->upload->update(
            [
                'number_success' => $this->upload->records()->where('status', UploadRecordStatusEnum::COMPLETE)->count(),
                'number_fails'   => $this->upload->records()->where('status', UploadRecordStatusEnum::FAILED)->count(),
            ]
        );
        $this->upload->refresh();

        if ($this->upload->user) {
            try {
                UploadExcelProgressEvent::dispatch($this->upload, $this->upload->user);
            } catch (\Exception $e) {
                // Ignore broadcast errors (e.g., WebSocket server not available)
                \Log::debug('Broadcast failed, continuing import', ['error' => $e->getMessage()]);
            }
        } elseif ($this->upload->webUser) {
            try {
                UploadRetinaExcelProgressEvent::dispatch($this->upload, $this->upload->webUser);
            } catch (\Exception $e) {
                // Ignore broadcast errors
                \Log::debug('Broadcast failed, continuing import', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function getFieldsFromRules($remove = [], $add = []): array
    {
        return array_merge(
            Arr::except(
                array_keys($this->rules()),
                $remove
            ),
            $add
        );
    }

}
