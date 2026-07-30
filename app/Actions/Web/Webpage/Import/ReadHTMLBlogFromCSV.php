<?php

/*
 * Author Louis Perez
 * Created on 30-07-2026-15h-16m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\Webpage\Import;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ReadHTMLBlogFromCSV
{
    use AsAction;

    public function handle(Command $command, Shop $shop): void
    {
        $filename = $command->argument('filename');

        $command->info("Reading Blog CSV from '$filename'");

        Excel::import(
            new BlogHTMLImport($shop),
            base_path($filename),
            null,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public string $commandSignature   = 'import:blog_from_csv {filename} {shop}';

    public function asCommand(Command $command)
    {
        if (!$command->argument(('shop'))) {
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

