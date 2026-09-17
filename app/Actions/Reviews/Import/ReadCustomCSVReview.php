<?php

/*
 * Author: Yudhistira A <aryarajasa0@gmail.com>
 * Created: Thu, 17 Sep 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Reviews\Import;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ReadCustomCSVReview
{
    use AsAction;

    public function handle(Command $command, Shop $shop): void
    {
        $filename = $command->argument('filename');

        $command->info("Reading from Custom reviews CSV from '$filename'");

        Excel::import(
            new CustomReviewImport($shop),
            base_path($filename),
            null,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public string $commandSignature = 'import:custom_review_csv {filename} {shop}';

    public function asCommand(Command $command): int
    {
        if (!$command->argument('shop')) {
            $command->error('Please select a shop');

            return 1;
        }

        if (!file_exists(base_path($command->argument('filename')))) {
            $command->error('File doesnt exists');

            return 1;
        }

        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $this->handle($command, $shop);

        return 0;
    }
}
