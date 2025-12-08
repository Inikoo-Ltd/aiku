<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Sep 2023 00:20:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Upload\ConvertUploadedFile;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Models\Helpers\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

trait WithImportModel
{
    use AsAction;
    use WithAttributes;

    private string $tmpPath = 'tmp/uploads/';

    private bool $isSync = false;

    public function __construct()
    {
        if (app()->environment('testing')) {
            $this->isSync = true;
        }
    }

    public function runImportForCommand($file, $command): Upload
    {
        return $this->handle($file);
    }

    // do this in parent class
    //    public function asController($parent, ActionRequest $request): Upload
    //    {
    //        $request->validate();
    //        $file = $request->file('file');
    //        Storage::disk('local')->put($this->tmpPath, $file);
    //
    //        return $this->handle($parent, $file);
    //    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $this->isSync = true;
        $filename = $command->argument('filename');
        $newFileName = now()->timestamp.'.xlsx';

        if ($command->option('g_drive')) {
            $googleDisk = Storage::disk('google');

            if (! $googleDisk->exists($filename)) {
                $command->error("$filename do not found in GDrive");

                return 1;
            }

            $content = $googleDisk->get($filename);
            Storage::disk('local')->put("tmp/$newFileName", $content);
            $filename = 'storage/app/tmp/'.$newFileName;
        }

        $file = ConvertUploadedFile::run($filename);
        $upload = $this->runImportForCommand($file, $command);

        Storage::disk('local')->delete('tmp/'.$newFileName);

        $command->table(
            ['', 'Success', 'Fail'],
            [
                [
                    $command->getName(),
                    $upload->number_success,
                    $upload->number_fails,
                ],
            ]
        );

        if ($upload->number_fails) {
            $failData = [];
            foreach ($upload->records()->where('status', UploadRecordStatusEnum::FAILED)->get() as $fail) {
                $failData[] = [$fail->row_number, implode($fail->errors)];
            }
            $command->table(
                ['Row', 'Error'],
                $failData
            );
        }

        return 0;
    }
}
