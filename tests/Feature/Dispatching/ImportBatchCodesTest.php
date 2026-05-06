<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Sun, 04 May 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace Tests\Feature;

use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Imports\Dispatching\BatchCodeImport;
use App\Models\Dispatching\BatchCode;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->warehouse    = createWarehouse();
    $this->organisation = $this->warehouse->organisation;

    $this->upload = Upload::create([
        'group_id'          => $this->warehouse->group_id,
        'organisation_id'   => $this->warehouse->organisation_id,
        'model'             => 'BatchCode',
        'parent_type'       => $this->warehouse->getMorphClass(),
        'parent_id'         => $this->warehouse->id,
        'original_filename' => 'test.xlsx',
        'filename'          => 'test.xlsx',
        'filesize'          => 0,
        'number_rows'       => 0,
        'number_success'    => 0,
        'number_fails'      => 0,
    ]);

    $this->import = new BatchCodeImport($this->warehouse, $this->upload);
});

function makeUploadRecord(Upload $upload): UploadRecord
{
    return $upload->records()->create([
        'values' => [],
        'status' => UploadRecordStatusEnum::PROCESSING,
    ]);
}

it('imports a batch code successfully when sku exists', function () {
    [$stocks] = createStocks($this->warehouse->group);
    [$orgStock] = createOrgStocks($this->organisation, [$stocks]);

    $row          = new Collection(['code' => 'BC-001', 'sku' => $orgStock->code, 'expiry_date' => null]);
    $uploadRecord = makeUploadRecord($this->upload);

    $this->import->storeModel($row, $uploadRecord);

    $uploadRecord->refresh();

    expect($uploadRecord->status)->toBe(UploadRecordStatusEnum::COMPLETE->value)
        ->and(BatchCode::where('code', 'BC-001')->exists())->toBeTrue();
});

it('marks record as failed with a clear message when sku is not found', function () {
    $row          = new Collection(['code' => 'BC-002', 'sku' => 'NON-EXISTENT-SKU', 'expiry_date' => null]);
    $uploadRecord = makeUploadRecord($this->upload);

    $this->import->storeModel($row, $uploadRecord);

    $uploadRecord->refresh();

    expect($uploadRecord->status)->toBe(UploadRecordStatusEnum::FAILED->value)
        ->and($uploadRecord->errors)->toContain("SKU 'NON-EXISTENT-SKU' not found.");
});
