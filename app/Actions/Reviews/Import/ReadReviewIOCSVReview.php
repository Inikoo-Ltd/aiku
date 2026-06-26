<?php

/*
 * Author Louis Perez
 * Created on 25-06-2026-12h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Reviews\Import;

use App\Actions\Reviews\Import\ReviewIOImport;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ReadReviewIOCSVReview
{
    use AsAction;

    public function handle(Command $command, Shop $shop): void
    {
        $filename = $command->argument('filename');

        $command->info("Reading from Review IO CSV from '$filename'");

        Excel::import(
            new ReviewIOImport($shop),
            Storage::disk('local')->path($filename),
            null,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public string $commandSignature   = 'import:review_io_csv {filename} {shop}';

    public function asCommand(Command $command)
    {
        if (!$command->argument(('shop'))) {
            $command->error('Please select a shop');
            return 1;
        }

        if (!Storage::disk('local')->exists($command->argument('filename'))) {
            $command->error('File doesnt exists');
            return 1;
        }

        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $this->handle($command, $shop);

        return 0;
    }
}
